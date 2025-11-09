<?php
declare(strict_types=1);
// ============================================================================
// ProjectPlayCore – Dezentraler Migrations-Runner (v3.1 mit API-Funktion)
// Pfad: /backend/modules/migrations.php
// Beschreibung:
//  - Führt migrations/*.php aller aktivierten Module aus
//  - Jeder Lauf ist idempotent (Status in /storage/migrations_state.json)
//  - Stellt Funktion ppc_migrations_run_for_enabled() bereit
// ============================================================================

require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../database/db.php';

// ----------------------------------------------------------------------------
// Hilfsfunktionen für Logging und Status
// ----------------------------------------------------------------------------
function ppc_mig_log(string $msg): void {
    $logDir = (defined('PPC_STORAGE') ? PPC_STORAGE : (__DIR__ . '/../../storage')) . '/logs';
    @mkdir($logDir, 0775, true);
    $logFile = $logDir . '/migrations.log';
    @file_put_contents($logFile, '[' . date('c') . '] ' . $msg . "\n", FILE_APPEND);
}

function ppc_mig_statefile(): string {
    return (defined('PPC_STORAGE') ? PPC_STORAGE : (__DIR__ . '/../../storage')) . '/migrations_state.json';
}

function ppc_mig_load_state(): array {
    $file = ppc_mig_statefile();
    if (is_file($file)) {
        $data = @file_get_contents($file);
        return json_decode($data ?: '{}', true) ?: [];
    }
    return [];
}

function ppc_mig_save_state(array $state): void {
    @file_put_contents(ppc_mig_statefile(), json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function ppc_mig_mark_applied(string $module, string $migration): void {
    $state = ppc_mig_load_state();
    if (!isset($state[$module])) $state[$module] = [];
    $state[$module][$migration] = date('c');
    ppc_mig_save_state($state);
}

function ppc_mig_is_applied(string $module, string $migration): bool {
    $state = ppc_mig_load_state();
    return isset($state[$module][$migration]);
}

// ----------------------------------------------------------------------------
// Hauptfunktion: ppc_migrations_run_for_enabled()
// ----------------------------------------------------------------------------
function ppc_migrations_run_for_enabled(PDO $db): array {
    $result = [
        'executed' => [],
        'skipped'  => [],
        'errors'   => [],
        'modules'  => [],
    ];

    $modulesDir = __DIR__;
    $enabledModules = [];

    foreach (glob($modulesDir . '/*/module.json') as $manifestPath) {
        $json = @file_get_contents($manifestPath);
        $manifest = json_decode($json, true);
        if (!is_array($manifest)) continue;
        if (!($manifest['enabled'] ?? false)) continue;

        $enabledModules[] = [
            'name' => $manifest['service'] ?? basename(dirname($manifestPath)),
            'path' => dirname($manifestPath),
        ];
    }

    $result['modules'] = array_column($enabledModules, 'name');

    foreach ($enabledModules as $mod) {
        $mDir = $mod['path'] . '/migrations';
        if (!is_dir($mDir)) continue;

        $files = glob($mDir . '/*.php') ?: [];
        natsort($files);

        foreach ($files as $file) {
            $name   = basename($file);
            $module = $mod['name'];

            if (ppc_mig_is_applied($module, $name)) {
                $result['skipped'][] = "{$module}/{$name}";
                continue;
            }

            try {
                ppc_mig_log("Running migration: {$module}/{$name}");
                $closure = require $file;
                if (!is_callable($closure)) {
                    throw new RuntimeException("Migration liefert keine Callable: {$module}/{$name}");
                }
                $closure($db);
                ppc_mig_mark_applied($module, $name);
                $result['executed'][] = "{$module}/{$name}";
            } catch (Throwable $t) {
                $result['errors'][] = "{$module}/{$name}: " . $t->getMessage();
                ppc_mig_log("Error: {$module}/{$name}: " . $t->getMessage());
            }
        }
    }

    return $result;
}

// ----------------------------------------------------------------------------
// CLI / Direktaufruf (z. B. aus Browser oder Cron)
// ----------------------------------------------------------------------------
if (php_sapi_name() !== 'cli') {
    $db = ppc_db();
    $res = ppc_migrations_run_for_enabled($db);

    header('Content-Type: text/plain; charset=utf-8');
    echo "=== ProjectPlayCore – Migration Runner (dezentral) ===\n";
    echo "Aktivierte Module: " . count($res['modules']) . "\n";
    echo "Ausgeführt: " . ($res['executed'] ? implode(', ', $res['executed']) : '—') . "\n";
    echo "Übersprungen: " . ($res['skipped'] ? implode(', ', $res['skipped']) : '—') . "\n";
    echo "Fehler: " . ($res['errors'] ? implode(' | ', $res['errors']) : '—') . "\n";
}
