<?php
// ============================================================================
// Modul: person
// Zweck: Initialisierung & Container-Registrierung für das Personenprofil
// Wird automatisch beim Systemstart geladen (über Core-Loader)
// ============================================================================

declare(strict_types=1);

// Container-Metadaten
define('PPC_MODULE_NAME', 'person');
define('PPC_MODULE_VERSION', '1.1.0');

// Header für Container-Health & Discovery
header('X-PPC-Module: person');
header('X-PPC-Container: active');

// Log-Verzeichnis
$logDir = dirname(__DIR__, 3) . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
}
@file_put_contents(
    $logDir . '/modules.log',
    '[' . date('c') . "] Modul 'person' geladen.\n",
    FILE_APPEND
);

// --- Manifest prüfen (aktiv/deaktiviert) ---
$manifestPath = __DIR__ . '/manifest.json';
if (is_file($manifestPath)) {
    $manifest = json_decode((string)file_get_contents($manifestPath), true);
    if (isset($manifest['enabled']) && $manifest['enabled'] === false) {
        @file_put_contents(
            $logDir . '/modules.log',
            '[' . date('c') . "] Modul 'person' ist deaktiviert und wurde übersprungen.\n",
            FILE_APPEND
        );
        return;
    }
}

// --- Storage-Struktur prüfen ---
$storageBase = dirname(__DIR__, 3) . '/storage/modules/person';
if (!is_dir($storageBase)) {
    @mkdir($storageBase, 0777, true);
}

// --- Health-Check-Funktion ---
if (!function_exists('person_health_check')) {
    function person_health_check(): array {
        $base = dirname(__DIR__, 3) . '/storage/modules/person';
        $ok = is_writable($base);
        return [
            'ok'       => $ok,
            'module'   => 'person',
            'version'  => PPC_MODULE_VERSION,
            'storage'  => $base,
            'errors'   => $ok ? [] : ['Storage nicht beschreibbar'],
            'time'     => time(),
        ];
    }
}

// --- Loader initialisieren ---
if (file_exists(__DIR__ . '/loader.php')) {
    require_once __DIR__ . '/loader.php';
}
