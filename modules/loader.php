<?php
// ============================================================================
// ProjectPlayCore – Module Loader (mit automatischer DB-Synchronisation)
// Pfad: /backend/modules/loader.php
// ============================================================================

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Basiskonfiguration und Utilities laden
// ---------------------------------------------------------------------------
if (!defined('PPC_CONFIG_LOADED')) {
    $root = dirname(__DIR__, 1); // /httpdocs/backend
    $cfg  = dirname($root) . '/config.php';
    if (!is_file($cfg)) {
        $doc = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
        if ($doc && is_file($doc . '/config.php')) $cfg = $doc . '/config.php';
    }
    if (!is_file($cfg)) {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(500);
        echo "FATAL: config.php nicht gefunden (modules/loader.php).";
        exit;
    }
    require_once $cfg;
}

require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../database/db.php';

// ---------------------------------------------------------------------------
// Log-Helfer
// ---------------------------------------------------------------------------
if (!function_exists('ppc_modules_log')) {
    function ppc_modules_log(string $msg): void {
        $dir = PPC_STORAGE . '/logs';
        @mkdir($dir, 0775, true);
        @file_put_contents($dir . '/modules.log', '[' . date('c') . "] " . $msg . PHP_EOL, FILE_APPEND);
    }
}

// ---------------------------------------------------------------------------
// Basisfunktionen
// ---------------------------------------------------------------------------
function ppc_modules_dir(): string {
    $path = realpath(__DIR__);
    return $path !== false ? $path : __DIR__;
}

function ppc_modules_manifest_path(string $moduleFolder): string {
    return rtrim($moduleFolder, '/').'/module.json';
}

function ppc_modules_read_manifest(string $moduleFolder): ?array {
    $path = ppc_modules_manifest_path($moduleFolder);
    if (!is_file($path)) { ppc_modules_log("manifest fehlt: {$path}"); return null; }
    $json = @file_get_contents($path);
    if ($json === false) { ppc_modules_log("manifest lesefehler: {$path}"); return null; }
    $data = json_decode($json, true);
    if (!is_array($data)) { ppc_modules_log("manifest json-fehler: {$path}"); return null; }
    return $data;
}

function ppc_module_manifest_by_name(string $name): ?array {
    $folder = ppc_modules_dir() . '/' . basename($name);
    if (!is_dir($folder)) return null;
    return ppc_modules_read_manifest($folder);
}

function ppc_modules_scan_folders(): array {
    $base = ppc_modules_dir();
    $entries = @scandir($base) ?: [];
    $out = [];

    // -----------------------------------------------------------------------
    // Debug-Log für Modulscan
    // -----------------------------------------------------------------------
    $logFile = PPC_STORAGE . '/logs/modules.log';
    @mkdir(dirname($logFile), 0775, true);
    @file_put_contents($logFile, "[".date('c')."] Starte Scan in: {$base}\n", FILE_APPEND);

    foreach ($entries as $e) {
        // ---------------------------------------------------------------
        // Nicht benötigte Einträge und Tools-Ordner überspringen
        // ---------------------------------------------------------------
        if (in_array($e, ['.', '..', 'loader.php', 'tools'], true)) continue;

        $full = $base . '/' . $e;
        if (is_dir($full)) {
            $out[] = $full;
            @file_put_contents($logFile, "[".date('c')."] Gefunden: {$full}\n", FILE_APPEND);
        }
    }

    sort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
}

function ppc_modules_ensure_table(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS modules (
            name VARCHAR(64) PRIMARY KEY,
            version VARCHAR(32) NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            installed_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// ============================================================================
// Automatische Datenbanksynchronisation pro Modul
// ============================================================================
if (!function_exists('ppc_modules_apply_database_schema')) {
    /**
     * Liest aus module.json -> database.tables.* und sorgt dafür, dass
     * Tabellen und Spalten automatisch angelegt/ergänzt werden.
     */
    function ppc_modules_apply_database_schema(PDO $db, string $moduleName, array $manifest): void
    {
        if (empty($manifest['database']['tables']) || !is_array($manifest['database']['tables'])) {
            return; // kein DB-Schema definiert
        }

        $logFile = PPC_STORAGE . '/logs/db_autogen.log';
        @mkdir(dirname($logFile), 0775, true);

        foreach ($manifest['database']['tables'] as $tableName => $tableDef) {
            if (empty($tableDef['columns']) || !is_array($tableDef['columns'])) continue;

            try {
                // Prüfen, ob Tabelle existiert
                $st = $db->prepare("SHOW TABLES LIKE :tbl");
                $st->execute([':tbl' => $tableName]);
                $exists = (bool)$st->fetchColumn();

                if (!$exists) {
                    // Tabelle komplett erstellen
                    $colsSql = [];
                    foreach ($tableDef['columns'] as $col => $def) {
                        $colsSql[] = "`{$col}` {$def}";
                    }
                    $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (" . implode(',', $colsSql) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
                    $db->exec($sql);
                    @file_put_contents($logFile, "[".date('c')."] [{$moduleName}] Tabelle erstellt: {$tableName}\n", FILE_APPEND);
                    continue;
                }

                // Tabelle existiert -> Spalten prüfen
                $existingCols = [];
                $cols = $db->query("SHOW COLUMNS FROM `{$tableName}`")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $c) {
                    $existingCols[strtolower($c['Field'])] = true;
                }

                foreach ($tableDef['columns'] as $col => $def) {
                    if (!isset($existingCols[strtolower($col)])) {
                        $sql = "ALTER TABLE `{$tableName}` ADD COLUMN `{$col}` {$def}";
                        $db->exec($sql);
                        @file_put_contents($logFile, "[".date('c')."] [{$moduleName}] Spalte hinzugefügt: {$tableName}.{$col} ({$def})\n", FILE_APPEND);
                    }
                }
            } catch (Throwable $t) {
                @file_put_contents($logFile, "[".date('c')."] [{$moduleName}] FEHLER bei {$tableName}: " . $t->getMessage() . "\n", FILE_APPEND);
            }
        }
    }
}

// ============================================================================
// Hauptfunktionen
// ============================================================================
function ppc_modules_sync(PDO $db): array {
    ppc_modules_ensure_table($db);
    $folders = ppc_modules_scan_folders();
    $synced  = [];

    foreach ($folders as $folder) {
        $manifest = ppc_modules_read_manifest($folder);
        if (!$manifest) continue;

        $name    = $manifest['service'] ?? basename($folder);
        $version = $manifest['version'] ?? '1.0.0';
        $enabled = (int)($manifest['enabled'] ?? 0);

        // Automatische Tabellen-Synchronisation, falls definiert
        if (!empty($manifest['database'])) {
            ppc_modules_apply_database_schema($db, $name, $manifest);
        }

        try {
            $db->prepare("INSERT INTO modules (name, version, enabled)
                          VALUES (:n, :v, :e)
                          ON DUPLICATE KEY UPDATE version=VALUES(version), enabled=VALUES(enabled)")
               ->execute([':n'=>$name, ':v'=>$version, ':e'=>$enabled]);
            $synced[] = $name;
        } catch (Throwable $t) {
            ppc_modules_log("sync fehler [{$name}]: " . $t->getMessage());
        }
    }

    return $synced;
}

function ppc_modules_set_enabled(PDO $db, string $name, bool $enabled): bool {
    try {
        ppc_modules_ensure_table($db);
        $stmt = $db->prepare("UPDATE modules SET enabled=:e WHERE name=:n");
        $stmt->execute([':e' => (int)$enabled, ':n' => $name]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $t) {
        ppc_modules_log("enable fehler [{$name}]: " . $t->getMessage());
        return false;
    }
}

function ppc_modules_list(PDO $db): array {
    try {
        ppc_modules_ensure_table($db);
        $stmt = $db->query("SELECT name, version, enabled FROM modules ORDER BY name ASC");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        return is_array($rows) ? $rows : [];
    } catch (Throwable $t) {
        ppc_modules_log("list fehler: " . $t->getMessage());
        return [];
    }
}

function ppc_module_admin_url(string $name, ?array $manifest = null): string {
    $m = $manifest ?? ppc_module_manifest_by_name($name) ?? [];
    $admin = $m['admin'] ?? [];
    $entry = $admin['entry'] ?? 'controller.php';
    if (is_string($entry) && strlen($entry) > 0 && $entry[0] === '/') {
        return $entry;
    }
    return "/backend/modules/" . rawurlencode($name) . "/" . ltrim((string)$entry, '/');
}

function ppc_modules_load_enabled(PDO $db): void {
    try {
        ppc_modules_ensure_table($db);
        $stmt = $db->query("SELECT name FROM modules WHERE enabled=1");
        $mods = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
        foreach ($mods as $name) {
            $loader = __DIR__ . '/' . $name . '/loader.php';
            if (is_file($loader)) {
                require_once $loader;
            } else {
                ppc_modules_log("kein loader.php für aktiviertes Modul: {$name}");
            }
        }
    } catch (Throwable $t) {
        ppc_modules_log("load_enabled fehler: " . $t->getMessage());
    }
}
