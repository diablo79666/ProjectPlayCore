<?php
// ============================================================================
// ProjectPlayCore – Status Controller (dezentralisiert)
// Pfad: /backend/modules/status/controller.php
// ============================================================================
declare(strict_types=1);

require_once __DIR__ . '/loader.php';

ppc_security_headers();
ppc_require_login();

$db   = ppc_db();
$user = ppc_current_user() ?? '';

// ============================================================================
// Berechtigungsprüfung
// ============================================================================
$mayView = false;
if (function_exists('ppc_user_can') && ppc_user_can('view_admin')) {
    $mayView = true;
} else {
    $st = $db->prepare("SELECT 1 FROM user_roles WHERE username=:u AND role='admin' LIMIT 1");
    $st->execute([':u'=>$user]);
    $mayView = (bool)$st->fetchColumn();
}
if (!$mayView) {
    http_response_code(403);
    echo "Zugriff verweigert (view_admin oder admin-Rolle erforderlich).";
    exit;
}

// ============================================================================
// Aktion verarbeiten
// ============================================================================
$action = strtolower((string)($_GET['action'] ?? 'about'));

// ============================================================================
// Ausgabe basierend auf Aktion
// ============================================================================
switch ($action) {
    case 'phpinfo':
        phpinfo();
        break;

    case 'log':
        $logFile = PPC_STORAGE . '/logs/core.log';
        echo '<pre>' . e(ppc_status_safe_tail($logFile, 200)) . '</pre>';
        break;

    case 'db':
        try {
            $db->query('SELECT 1');
            echo "✅ Datenbankverbindung aktiv.";
        } catch (Throwable $t) {
            echo "❌ DB-Fehler: " . e($t->getMessage());
        }
        break;

    case 'about':
    default:
        echo "<h3>ProjectPlayCore Statusmodul</h3>";
        echo "<p>Version 1.0 – Anzeigen von PHP-, DB- und Log-Informationen.</p>";
        break;
}
