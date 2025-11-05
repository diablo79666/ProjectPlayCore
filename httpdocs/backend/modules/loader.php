<?php
/**
 * ProjectPlayCore – Backend Module Loader
 * PHP 8.2
 *
 * Funktionen:
 *  - ppc_modules_sync(PDO $db):         scannt /backend/modules, liest manifest.json, aktualisiert DB (und triggert ggf. Migrationen)
 *  - ppc_modules_set_enabled(PDO $db, string $name, bool $enabled) (triggert bei Enable Migrationen)
 *  - ppc_modules_list(PDO $db):         Liste aus DB
 *  - ppc_modules_load_enabled(PDO $db): lädt loader.php aller aktivierten Module
 *  - ppc_modules_dashboard_buttons(PDO $db): liefert Dashboard-Buttons aus den Manifests aktivierter Module
 *  - ppc_module_admin_url(string $name, ?array $manifest=null): berechnet Admin-URL eines Moduls
 *
 * Logging: PPC_STORAGE/logs/modules.log
 */

declare(strict_types=1);

// Kern laden
if (!defined('PPC_CONFIG_LOADED')) {
    $root = dirname(__DIR__, 1); // /httpdocs/backend
    $cfg  = dirname($root) . '/config.php'; // /httpdocs/config.php
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

// Log-Helfer
if (!function_exists('ppc_modules_log')) {
    function ppc_modules_log(string $msg): void {
        $dir = PPC_STORAGE . '/logs';
        @mkdir($dir, 0775, true);
        @file_put_contents($dir . '/modules.log', '[' . date('c') . "] " . $msg . PHP_EOL, FILE_APPEND);
    }
}

function ppc_modules_dir(): string {
    return __DIR__; // /httpdocs/backend/modules
}

function ppc_modules_manifest_path(string $moduleFolder): string {
    return rtrim($moduleFolder, '/').'/manifest.json';
}

function ppc_modules_read_manifest(string $moduleFolder): ?array {
    $path = ppc_modules_manifest_path($moduleFolder);
    if (!is_file($path)) { ppc_modules_log("manifest fehlt: {$path}"); return null; }
    $json = @file_get_contents($path);
    if ($json === false) { ppc_modules_log("manifest lesefehler: {$path}"); return null; }
    $data = json_decode($json, true);
    if (!is_array($data)) { ppc_modules_log("manifest json-fehler: {$path}"); return null; }

    $name    = isset($data['name']) ? trim((string)$data['name']) : '';
    $version = isset($data['version']) ? trim((string)$data['version']) : '1.0.0';
    $enabled = isset($data['enabled']) ? (bool)$data['enabled'] : false;

    if ($name === '') { ppc_modules_log("manifest ohne name: {$path}"); return null; }

    // Admin-Block normalisieren
    $admin = $data['admin'] ?? [];
    if (!is_array($admin)) $admin = [];
    $adminEntry  = isset($admin['entry']) ? trim((string)$admin['entry']) : 'controller.php';
    $adminLabel  = isset($admin['label']) ? trim((string)$admin['label']) : ucfirst($name);
    $adminIcon   = isset($admin['icon'])  ? trim((string)$admin['icon'])  : 'settings';
    $adminCap    = isset($admin['capability']) ? trim((string)$admin['capability']) : '';
    $adminOrder  = isset($admin['order']) ? (int)$admin['order'] : 100;
    $adminVisible= isset($admin['visible']) ? (bool)$admin['visible'] : true;

    $admin = [
        'entry'      => $adminEntry,
        'label'      => $adminLabel,
        'icon'       => $adminIcon,
        'capability' => $adminCap,
        'order'      => $adminOrder,
        'visible'    => $adminVisible,
    ];

    $data['name']    = $name;
    $data['version'] = $version;
    $data['enabled'] = $enabled;
    $data['admin']   = $admin;

    return $data;
}

/**
 * Manifest eines Moduls nach Name laden (falls Ordner vorhanden).
 */
function ppc_module_manifest_by_name(string $name): ?array {
    $folder = ppc_modules_dir() . '/' . basename($name);
    if (!is_dir($folder)) return null;
    return ppc_modules_read_manifest($folder);
}

function ppc_modules_scan_folders(): array {
    $base = ppc_modules_dir();
    $entries = @scandir($base) ?: [];
    $out = [];
    foreach ($entries as $e) {
        if ($e === '.' || $e === '..' || $e === 'loader.php') continue;
        $full = $base . '/' . $e;
        if (is_dir($full)) $out[] = $full;
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

/**
 * Interner Helfer: Migrationen ausführen (einheitlicher Trigger)
 * – nutzt den bestehenden, zentralen Runner; keine Duplikation der Logik.
 */
function ppc_modules_trigger_migrations_if_available(): void {
    $runner = __DIR__ . '/migrations.php';
    if (is_file($runner)) {
        require_once $runner;
        if (function_exists('ppc_migrations_run_for_enabled')) {
            ppc_migrations_run_for_enabled(); // migriert nur aktivierte Module (Single-Source)
        }
    }
}

function ppc_modules_sync(PDO $db): array {
    ppc_modules_ensure_table($db);
    $folders = ppc_modules_scan_folders();
    $synced  = [];
    $versionChanged = false; // einheitlicher Trigger am Ende

    foreach ($folders as $folder) {
        $manifest = ppc_modules_read_manifest($folder);
        if (!$manifest) continue;

        $name    = $manifest['name'];
        $version = $manifest['version'];
        $enabled = (int)$manifest['enabled'];

        // alte Version (falls vorhanden) ermitteln
        $oldVer = null;
        try {
            $st = $db->prepare("SELECT version FROM modules WHERE name=:n LIMIT 1");
            $st->execute([':n'=>$name]);
            $oldVer = $st->fetchColumn();
            $oldVer = $oldVer !== false ? (string)$oldVer : null;
        } catch (Throwable $t) {
            ppc_modules_log("read version fehler [{$name}]: " . $t->getMessage());
        }

        try {
            $db->prepare("INSERT INTO modules (name, version, enabled)
                          VALUES (:n, :v, :e)
                          ON DUPLICATE KEY UPDATE version=VALUES(version)")
               ->execute([':n'=>$name, ':v'=>$version, ':e'=>$enabled]);

            // initialen enabled-Wert nur beim ersten Eintrag setzen
            $db->prepare("UPDATE modules SET enabled=:e WHERE name=:n AND installed_at IS NULL")
               ->execute([':e'=>$enabled, ':n'=>$name]);

            // Version-Diff merken (nur wenn es vorher schon einen Eintrag gab)
            if ($oldVer !== null && $oldVer !== $version) {
                $versionChanged = true;
                ppc_modules_log("version change erkannt [{$name}] {$oldVer} → {$version}");
            }

            $synced[] = $name;
        } catch (Throwable $t) {
            ppc_modules_log("sync fehler [{$name}]: " . $t->getMessage());
        }
    }

    // EIN Trigger für alle Version-Änderungen dieses Sync-Laufs
    if ($versionChanged) {
        ppc_modules_trigger_migrations_if_available();
    }

    return $synced;
}

function ppc_modules_set_enabled(PDO $db, string $name, bool $enabled): bool {
    try {
        ppc_modules_ensure_table($db);
        $stmt = $db->prepare("UPDATE modules SET enabled=:e WHERE name=:n");
        $stmt->execute([':e' => (int)$enabled, ':n' => $name]);
        $ok = $stmt->rowCount() > 0;

        // Bei erfolgreichem Enable: Migrationen der aktivierten Module ausführen
        if ($ok && $enabled === true) {
            ppc_modules_log("enable trigger migration [{$name}]");
            ppc_modules_trigger_migrations_if_available();
        }

        return $ok;
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

/**
 * Admin-URL für ein Modul bestimmen (relativ/absolut):
 * - Beginnt entry mit '/', wird er als absolute URL verwendet.
 * - Sonst: /backend/modules/<name>/<entry>
 */
function ppc_module_admin_url(string $name, ?array $manifest = null): string {
    $m = $manifest ?? ppc_module_manifest_by_name($name) ?? [];
    $admin = $m['admin'] ?? [];
    $entry = $admin['entry'] ?? 'controller.php';
    if (is_string($entry) && strlen($entry) > 0 && $entry[0] === '/') {
        return $entry;
    }
    return "/backend/modules/" . rawurlencode($name) . "/" . ltrim((string)$entry, '/');
}

/**
 * Dashboard-Buttons aus den Manifests aktivierter Module erzeugen.
 * Jeder Button: ['name','label','href','icon','order','visible','capability']
 * Capability-Filter (falls Rollen-System geladen): nur aufnehmen, wenn erfüllt.
 */
function ppc_modules_dashboard_buttons(PDO $db): array {
    $buttons = [];
    try {
        ppc_modules_ensure_table($db);
        $stmt = $db->query("SELECT name FROM modules WHERE enabled=1");
        $mods = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
        foreach ($mods as $name) {
            $manifest = ppc_module_manifest_by_name($name);
            if (!$manifest) continue;

            $admin = $manifest['admin'] ?? [];
            if (!($admin['visible'] ?? true)) continue;

            $cap = (string)($admin['capability'] ?? '');
            if ($cap !== '' && function_exists('ppc_user_has_cap')) {
                // Falls Rollen-/Cap-System aktiv ist, respect capability
                if (!ppc_user_has_cap($cap)) {
                    continue;
                }
            }

            $buttons[] = [
                'name'       => $manifest['name'],
                'label'      => (string)$admin['label'],
                'href'       => ppc_module_admin_url($manifest['name'], $manifest),
                'icon'       => (string)$admin['icon'],
                'order'      => (int)$admin['order'],
                'visible'    => (bool)$admin['visible'],
                'capability' => $cap,
            ];
        }
    } catch (Throwable $t) {
        ppc_modules_log("dashboard_buttons fehler: " . $t->getMessage());
    }

    // Stabil sortieren nach order, dann label
    usort($buttons, function(array $a, array $b): int {
        $cmp = ($a['order'] <=> $b['order']);
        if ($cmp !== 0) return $cmp;
        return strcasecmp($a['label'], $b['label']);
    });

    return $buttons;
}

/**
 * Lädt für alle aktivierten Module deren loader.php,
 * damit globale Helfer (z. B. Rollen/Cap-Checks) verfügbar sind.
 */
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
