<?php
/**
 * Module: person – Loader
 * Legt Tabelle person_profile an (Self-Entry), wenn KYC deaktiviert ist.
 * Bei KYC aktiviert werden Felder NICHT beschrieben; UI zeigt read-only.
 */

declare(strict_types=1);

if (!defined('PPC_CONFIG_LOADED')) {
    $root = dirname(__DIR__, 1);        // /httpdocs/backend
    $cfg  = dirname($root) . '/config.php';
    if (!is_file($cfg)) {
        $doc = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
        if ($doc && is_file($doc . '/config.php')) $cfg = $doc . '/config.php';
    }
    if (!is_file($cfg)) {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(500);
        echo "FATAL: config.php nicht gefunden (person/loader.php).";
        exit;
    }
    require_once $cfg;
}

require_once __DIR__ . '/../../database/db.php';

try {
    $db = ppc_db();
    // Self-Entry-Profil (privat)
    $db->exec("
        CREATE TABLE IF NOT EXISTS person_profile (
            username    VARCHAR(190) PRIMARY KEY,
            realname    VARCHAR(190) NULL,
            dob         DATE NULL,                 -- nur Self-Entry; bei KYC wird users.dob verwendet
            street      VARCHAR(190) NULL,
            zip         VARCHAR(32)  NULL,
            city        VARCHAR(190) NULL,
            country     VARCHAR(2)   NULL,
            created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Throwable $t) {
    // still silent – UI zeigt Fehler später
}
