<?php
/**
 * Module: template
 * Loader: führt einmalige Initialisierungen beim Laden aus.
 * Voraussetzungen: Admin-Login; Cap-Checks in controller.php.
 */

require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/utils.php';
require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../../../config.php';

ppc_security_headers();

// Optionale Initialisierung (z. B. Tabellen anlegen) – hier nur als Vorlage.
try {
    $db = ppc_db();
    // Beispiel: einfache Key/Value-Tabelle für spätere Module
    // $db->exec("CREATE TABLE IF NOT EXISTS template_kv (
    //   k VARCHAR(64) PRIMARY KEY,
    //   v TEXT,
    //   updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    // )");
} catch (Throwable $e) {
    // Fehler nur loggen, Admin-UI zeigt Details im Controller bei Bedarf
    @file_put_contents(PPC_STORAGE . '/logs/modules.log',
        '[' . date('Y-m-d H:i:s') . "] [template.loader] " . $e->getMessage() . PHP_EOL,
        FILE_APPEND
    );
}
