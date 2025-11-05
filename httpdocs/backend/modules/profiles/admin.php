<?php
// ============================================================================
// Modul: profiles – Admin-Entry (Container-Einstiegspunkt)
// Leitet auf controller.php weiter, damit kein doppelter Code entsteht
// ============================================================================

declare(strict_types=1);

require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/utils.php';
require_once __DIR__ . '/../../../config.php';

ppc_security_headers();
ppc_require_login();

if (!function_exists('ppc_user_can') || !ppc_user_can('view_admin')) {
    http_response_code(403);
    exit('Zugriff verweigert (view_admin erforderlich).');
}

$action = (string)($_GET['action'] ?? 'index');
header('Location: /backend/modules/profiles/controller.php?action=' . urlencode($action));
exit;
