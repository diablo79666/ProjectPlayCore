<?php
// ============================================================================
// Modul: hello
// Zweck: Beispielmodul – zeigt, wie ein Modul initialisiert und als Container erkannt wird
// Hinweis: Wird automatisch beim Systemstart geladen (über Core-Loader)
// ============================================================================

declare(strict_types=1);

// Container-Metadaten (für Health & Discovery)
define('PPC_MODULE_NAME', 'hello');
define('PPC_MODULE_VERSION', '1.1.0');
header('X-PPC-Module: hello');
header('X-PPC-Container: active');

// Beim Laden einmal in ein Log schreiben:
$logDir = dirname(__DIR__, 3) . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
}
@file_put_contents(
    $logDir . '/modules.log',
    '[' . date('c') . "] Modul 'hello' geladen.\n",
    FILE_APPEND
);

// Optional: Beispiel-Funktion (kann später vom Admin-Dashboard aufgerufen werden)
if (!function_exists('hello_widget_render')) {
    function hello_widget_render(): void {
        echo "<p>👋 Hallo vom Modul <strong>hello</strong>!</p>";
    }
}
