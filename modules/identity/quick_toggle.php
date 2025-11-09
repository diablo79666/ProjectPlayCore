<?php
/**
 * Identity – Quick Toggle (für Admin-Dashboard)
 * Schaltet identity_settings.enabled zwischen 0/1 hin und her und leitet zurück.
 *
 * Zugriff: erlaubt, wenn EINE Bedingung stimmt:
 *  - ppc_user_can('view_admin')  ODER
 *  - Rolle 'admin'               ODER
 *  - Rolle 'superadmin'
 *
 * Redirect-Ziel: via ?redirect=/backend/ (Fallback: /backend/)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/utils.php';
require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../roles/loader.php'; // falls verfügbar

ppc_security_headers();
ppc_require_login();

$db   = ppc_db();
$user = (string)(ppc_current_user() ?? '');

// --- Access-Check ---
$allowed = false;
try {
    if (function_exists('ppc_user_can') && ppc_user_can('view_admin')) $allowed = true;
    if (!$allowed && function_exists('ppc_has_role') && ppc_has_role('admin')) $allowed = true;
    if (!$allowed && function_exists('ppc_has_role') && ppc_has_role('superadmin')) $allowed = true;
} catch (Throwable $t) {}

if (!$allowed) {
    http_response_code(403);
    echo '403 – view_admin erforderlich';
    exit;
}

// --- CSRF prüfen ---
$csrfCookie = 'identity_toggle_csrf';
$cookie     = (string)($_COOKIE[$csrfCookie] ?? '');
$param      = (string)($_GET['csrf'] ?? $_POST['csrf'] ?? '');
if (!$cookie || !$param || !hash_equals($cookie, $param)) {
    http_response_code(403);
    echo '403 – CSRF ungültig';
    exit;
}

// --- Table ensure ---
try {
    $db->exec("CREATE TABLE IF NOT EXISTS identity_settings (
        skey   VARCHAR(64) PRIMARY KEY,
        svalue TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $t) {
    http_response_code(500);
    echo 'DB-Fehler (identity_settings): ' . e($t->getMessage());
    exit;
}

// --- aktuellen Status lesen ---
$enabled = '0';
try {
    $st = $db->prepare("SELECT svalue FROM identity_settings WHERE skey='enabled' LIMIT 1");
    $st->execute();
    $val = $st->fetchColumn();
    $enabled = ($val !== false) ? (string)$val : '0';
} catch (Throwable $t) {}

// --- toggeln ---
$next = ($enabled === '1') ? '0' : '1';
try {
    $st = $db->prepare("INSERT INTO identity_settings(skey,svalue) VALUES('enabled',:v)
                        ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)");
    $st->execute([':v' => $next]);
} catch (Throwable $t) {
    http_response_code(500);
    echo 'DB-Fehler (toggle): ' . e($t->getMessage());
    exit;
}

// --- Redirect ---
$redirect = (string)($_GET['redirect'] ?? $_POST['redirect'] ?? '/backend/');
if (!preg_match('~^/~', $redirect)) $redirect = '/backend/';
header('Location: ' . $redirect, true, 302);
exit;
