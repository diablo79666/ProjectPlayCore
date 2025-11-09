<?php
// ============================================================================
// Modul: identity
// Zweck: Initialisierung & Container-Registrierung für das Identity/KYC-Modul
// Wird automatisch beim Systemstart geladen (über Core-Loader)
// ============================================================================

declare(strict_types=1);

// Container-Metadaten
define('PPC_MODULE_NAME', 'identity');
define('PPC_MODULE_VERSION', '1.2.0');

// Header für Container-Health & Discovery
header('X-PPC-Module: identity');
header('X-PPC-Container: active');

// Log-Verzeichnis
$logDir = dirname(__DIR__, 3) . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
}

// Log-Eintrag beim Laden
@file_put_contents(
    $logDir . '/modules.log',
    '[' . date('c') . "] Modul 'identity' geladen.\n",
    FILE_APPEND
);

// --- Manifest-Status prüfen (aktiv/deaktiviert) ---
$manifestPath = __DIR__ . '/manifest.json';
if (is_file($manifestPath)) {
    $manifest = json_decode((string)file_get_contents($manifestPath), true);
    if (isset($manifest['enabled']) && $manifest['enabled'] === false) {
        @file_put_contents(
            $logDir . '/modules.log',
            '[' . date('c') . "] Modul 'identity' ist deaktiviert und wurde übersprungen.\n",
            FILE_APPEND
        );
        return;
    }
}

// --- Storage-Struktur prüfen ---
$storageBase = dirname(__DIR__, 3) . '/storage/modules/identity';
if (!is_dir($storageBase)) {
    @mkdir($storageBase, 0777, true);
    @mkdir($storageBase . '/pending', 0777, true);
    @mkdir($storageBase . '/logs', 0777, true);
}

// --- Health-Check-Funktion (für Container-Cleanup) ---
if (!function_exists('identity_health_check')) {
    function identity_health_check(): array {
        $base = dirname(__DIR__, 3) . '/storage/modules/identity';
        $ok = is_writable($base);
        $errors = $ok ? [] : ['Storage nicht beschreibbar'];
        return [
            'ok'       => $ok,
            'module'   => 'identity',
            'version'  => PPC_MODULE_VERSION,
            'storage'  => $base,
            'errors'   => $errors,
            'time'     => time()
        ];
    }
}

// --- Loader initialisieren (wenn aktiv) ---
if (file_exists(__DIR__ . '/loader.php')) {
    require_once __DIR__ . '/loader.php';
}
