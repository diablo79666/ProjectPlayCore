<?php
/**
 * ProjectPlayCore – config.php
 * PHP 8.2 kompatibel
 * Setzt Basis-Konstanten und wählt automatisch einen beschreibbaren STORAGE-Pfad.
 */

declare(strict_types=1);

// Kennzeichen, damit andere Includes prüfen können
if (!defined('PPC_CONFIG_LOADED')) {
    define('PPC_CONFIG_LOADED', true);
}

// =========================================================================
// Basis-Pfade
// =========================================================================
define('PPC_ROOT',    rtrim(__DIR__, '/'));
define('PPC_BACKEND', PPC_ROOT . '/backend');
define('PPC_FRONTEND',PPC_ROOT . '/frontend');

// Basis-URL heuristisch (http/https + Host)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('PPC_BASE_URL', $scheme . '://' . $host);

// App-Metadaten
define('PPC_APP_NAME', 'ProjectPlayCore Web');
define('PPC_VERSION',  '1.0.0');
define('PPC_ENV',      'prod'); // 'dev' für Debug-Ausgaben

// =========================================================================
// STORAGE: automatisch wählbarer, beschreibbarer Pfad
// Reihenfolge (typisch Plesk):
//   1) /httpdocs/storage
//   2) /private/ppc_storage
//   3) /tmp/ppc_storage
//   4) sys_get_temp_dir()/ppc_storage
// =========================================================================
function ppc_pick_storage_dir(): string {
    $root = PPC_ROOT;
    $candidates = [
        $root . '/storage',
        dirname($root) . '/private/ppc_storage',
        dirname($root) . '/tmp/ppc_storage',
        rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/ppc_storage',
    ];

    foreach ($candidates as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            @mkdir($dir . '/sessions', 0775, true);
            @mkdir($dir . '/logs',     0775, true);
            @mkdir($dir . '/modules',  0775, true);
            @file_put_contents($dir . '/index.html', '');
            @file_put_contents($dir . '/.htaccess', "Options -Indexes\n");
            return $dir;
        }
    }
    // Fallback (nicht ideal)
    return $root . '/storage';
}

define('PPC_STORAGE', ppc_pick_storage_dir());

// Warnung loggen, falls nicht beschreibbar
if (!is_dir(PPC_STORAGE) || !is_writable(PPC_STORAGE)) {
    @file_put_contents(
        PPC_ROOT . '/_storage_warning.log',
        '[' . date('c') . '] STORAGE not writable: ' . PPC_STORAGE . PHP_EOL,
        FILE_APPEND
    );
}

