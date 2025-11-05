<?php
// ============================================================================
// ProjectPlayCore – Admin Interface (Container) – Hello-Modul
// Pfad: /backend/modules/hello/admin.php
// ============================================================================

declare(strict_types=1);
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/utils.php';
require_once __DIR__ . '/../../../config.php';

ppc_security_headers();
ppc_require_login();

// --- Zugriff prüfen ---
if (!function_exists('ppc_user_can') || !ppc_user_can('view_admin')) {
    http_response_code(403);
    echo "Zugriff verweigert (view_admin erforderlich).";
    exit;
}

// --- Weiterleitung zur bestehenden Controller-Logik ---
$action = $_GET['action'] ?? 'settings';
header("Location: /backend/modules/hello/controller.php?action=" . urlencode($action));
exit;
