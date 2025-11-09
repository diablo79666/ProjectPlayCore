<?php
// ============================================================================
// Modul: backup
// Zweck: Initialisierung & Container-Registrierung für das Backup-Modul
// Wird automatisch beim Systemstart geladen (über Core-Loader)
// ============================================================================

declare(strict_types=1);

// Container-Metadaten
define('PPC_MODULE_NAME', 'backup');
define('PPC_MODULE_VERSION', '1.1.0');

// Header für Container-Health & Discovery
header('X-PPC-Module: backup');
header('X-PPC-Container: active');

// Log-Verzeichnis anlegen
$logDir = dirname(__DIR__, 3) . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
}

// Log-Eintrag beim Laden
@file_put_contents(
    $logDir . '/modules.log',
    '[' . date('c') . "] Modul 'backup' geladen.\n",
    FILE_APPEND
);

// Optionale Health-Funktion (kann vom Container-Cleanup verwendet werden)
if (!function_exists('backup_health_check')) {
    function backup_health_check(): array {
        $status = [
            'ok'       => true,
            'module'   => 'backup',
            'version'  => PPC_MODULE_VERSION,
            'storage'  => is_writable(dirname(__DIR__, 3) . '/storage/backups'),
            'timestamp'=> time()
        ];
        return $status;
    }
}
