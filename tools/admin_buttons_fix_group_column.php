<?php
// @admin-button: Admin Buttons – Gruppen-Spalten Fix | /backend/tools/admin_buttons_fix_group_column.php | System | 12
/**
 * ProjectPlayCore – Admin-Buttons Tabelle fixieren
 * Fügt die fehlende group_name-Spalte hinzu (falls nicht vorhanden)
 * und erstellt anschließend den Cleanup-Button.
 */

declare(strict_types=1);
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../../config.php';

ppc_security_headers();
ppc_require_login();

$db = ppc_db();
$log = [];

try {
    // Prüfe Spalten
    $cols = [];
    $st = $db->query("SHOW COLUMNS FROM admin_buttons");
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $cols[strtolower($c['Field'])] = true;
    }

    // Spalte group_name nachrüsten
    if (!isset($cols['group_name'])) {
        $db->exec("ALTER TABLE admin_buttons ADD COLUMN group_name VARCHAR(100) NULL AFTER href");
        $log[] = "🧩 Spalte 'group_name' wurde hinzugefügt.";
    } else {
        $log[] = "ℹ️ Spalte 'group_name' ist bereits vorhanden.";
    }

    // Button hinzufügen oder aktualisieren
    $db->exec("
        INSERT INTO admin_buttons (area, title, href, group_name, sort_order, required_cap, enabled, source_type, source_id)
        VALUES ('dashboard', 'Container Health & Cleanup', '/backend/tools/container_health_cleanup.php', 'System', 120, 'view_admin', 1, 'local', 'core')
        ON DUPLICATE KEY UPDATE title=VALUES(title), href=VALUES(href), enabled=1
    ");
    $log[] = "✅ Button 'Container Health & Cleanup' erfolgreich angelegt.";
} catch (Throwable $t) {
    $log[] = "❌ Fehler: " . $t->getMessage();
}

?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Admin-Buttons Fix</title>
<style>
body{background:#0e0e0e;color:#eee;font-family:ui-monospace,Consolas,monospace;padding:2rem}
.ok{color:#9fe3bd}.err{color:#f88}
</style>
</head>
<body>
<h2>Admin-Buttons Strukturprüfung</h2>
<ul>
<?php foreach ($log as $l): ?>
  <li><?= htmlspecialchars($l, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
<?php endforeach; ?>
</ul>
<p><a href="/backend/">Zum Admin-Dashboard</a></p>
</body>
</html>
