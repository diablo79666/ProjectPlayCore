<?php
// ============================================================================
// Modul: identity – Controller
// Aktionen:
//   - action=index        → Status & Button „Ausweis prüfen“
//   - action=kyc_start    → Start beim aktiven Provider
//   - action=kyc_callback → Rückkehr vom Provider
//   - action=kyc_webhook  → Webhook-Endpoint
// ============================================================================

declare(strict_types=1);
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/utils.php';
require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../../../config.php';

// ------------------- Container-Metadaten -------------------
define('PPC_MODULE_NAME', 'identity');
define('PPC_MODULE_VERSION', '1.2.0');
header('X-PPC-Module: identity');
header('X-PPC-Container: active');

// ------------------- Sicherheitsprüfungen -------------------
ppc_security_headers();
ppc_require_login();

$db   = ppc_db();
$user = (string)(ppc_current_user() ?? '');

// ------------------- Funktionsblock -------------------
function identity_get_setting(PDO $db, string $k, string $def=''): string {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS identity_settings (
            skey VARCHAR(64) PRIMARY KEY,
            svalue TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $st = $db->prepare("SELECT svalue FROM identity_settings WHERE skey=:k LIMIT 1");
        $st->execute([':k'=>$k]);
        $v = $st->fetchColumn();
        return ($v !== false) ? (string)$v : $def;
    } catch (Throwable $t) {
        return $def;
    }
}

function identity_current_provider(PDO $db): string {
    $p = strtolower(identity_get_setting($db, 'provider', 'demo'));
    return preg_replace('~[^a-z0-9_\-]~i', '', $p ?: 'demo');
}

function identity_load_adapter(PDO $db): void {
    $name = identity_current_provider($db);
    $file = __DIR__ . "/providers/{$name}/adapter.php";
    if (!is_file($file)) {
        $file = __DIR__ . "/providers/demo/adapter.php";
    }
    require_once $file;
}

// ------------------- Router -------------------
$action = strtolower((string)($_GET['action'] ?? $_POST['action'] ?? 'index'));
identity_load_adapter($db);

switch ($action) {
    case 'kyc_start':
        identity_start($user);
        break;

    case 'kyc_callback':
        $verificationId = (string)($_GET['verification_id'] ?? '');
        identity_callback($user, $verificationId);
        break;

    case 'kyc_webhook':
        $verificationId = (string)($_GET['verification_id'] ?? $_POST['verification_id'] ?? '');
        $status         = (string)($_GET['status'] ?? $_POST['status'] ?? '');
        identity_webhook($user, $verificationId, $status);
        exit;

    case 'index':
    default:
        identity_render_status($db, $user);
        break;
}
