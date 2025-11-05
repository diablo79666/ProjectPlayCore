<?php
/**
 * Module: status – Controller
 */
declare(strict_types=1);

require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/utils.php';
require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/loader.php';

ppc_security_headers();
ppc_require_login();

$db   = ppc_db();
$user = ppc_current_user() ?? '';

$hasApi = function_exists('ppc_user_can');
$mayView = $hasApi ? ppc_user_can('view_admin') : false;
if (!$mayView) {
    $st = $db->prepare("SELECT 1 FROM user_roles WHERE username=:u AND role='admin' LIMIT 1");
    $st->execute([':u'=>$user]);
    $mayView = (bool)$st->fetchColumn();
}
if (!$mayView) {
    http_response_code(403);
    echo "Zugriff verweigert (view_admin oder admin-Rolle erforderlich).";
    exit;
}

$action = strtolower((string)($_GET['action'] ?? 'about'));
?>
