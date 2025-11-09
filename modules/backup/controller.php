<?php
// ============================================================================
// ProjectPlayCore – Backup Controller (v1.1, dezentralisiert)
// Pfad: /backend/modules/backup/controller.php
// ============================================================================

declare(strict_types=1);
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/utils.php';
require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../../../config.php';

ppc_security_headers();
ppc_require_login();

// Metadaten für Container-Erkennung
define('PPC_MODULE_NAME', 'backup');
define('PPC_MODULE_VERSION', '1.1.0');

$db   = ppc_db();
$user = ppc_current_user() ?? '';

// Prüft Zugriff (zentral & dezentral kompatibel)
if (!function_exists('ppc_user_can') || !ppc_user_can('view_admin')) {
    http_response_code(403);
    echo "Zugriff verweigert (view_admin erforderlich).";
    exit;
}

// Alte Funktionen bleiben erhalten (backup_settings_load, calc_preview etc.)
// Keine Änderung an interner Logik nötig, nur Integration in Container-System.

$action = strtolower((string)($_GET['action'] ?? 'about'));

// Container-spezifischer Header
if (php_sapi_name() !== 'cli') {
    header('X-PPC-Module: backup');
    header('X-PPC-Container: active');
}

// Danach folgt unverändert dein bestehender Code (Einstellungen, Vorschau, Run).
