<?php
// ============================================================================
// ProjectPlayCore – Template Loader (dezentralisiert)
// Pfad: /backend/modules/template/loader.php
// ============================================================================
declare(strict_types=1);
require_once __DIR__ . '/../../core/init.php';

use Core\Container;

ppc_security_headers();

// ============================================================================
// Initialisierung (z. B. Tabellen, Dateien, Logs)
// ============================================================================
try {
    $db = ppc_db();
    // Platzhalter: spätere Erweiterungen (Tabellen, Strukturen etc.)
} catch (Throwable $e) {
    @file_put_contents(PPC_STORAGE . '/logs/modules.log',
        '[' . date('Y-m-d H:i:s') . "] [template.loader] " . $e->getMessage() . PHP_EOL,
        FILE_APPEND
    );
}

// ============================================================================
// Container-Registrierung
// ============================================================================
Container::register('template', fn() => true);
