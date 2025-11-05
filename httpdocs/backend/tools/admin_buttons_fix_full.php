<?php
/**
 * ProjectPlayCore – Admin-Buttons Fix (Vollversion)
 * Ergänzt fehlende Spalten: group_name, source_type, source_id, last_seen, health
 * und legt danach den Button "Container Health & Cleanup" an.
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
    $cols = [];
    $st = $db->query("SHOW COLUMNS FROM admin_buttons");
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $cols[strtolower($c['Field'])] = true;
    }

    // Fehlende Spalten ergänzen
    if (!isset($cols['group_name'])) {
        $db->exec("ALTER TABLE admin_buttons ADD COLUMN group_name VARCHAR(100) NULL AFTER href");
        $log[] = "🧩 Spalte 'group_name' wurde hinzugefügt.";
    }
    if (!isset($cols['source_type'])) {
        $db->exec("ALTER TABLE admin_buttons ADD COLUMN source_type VARCHAR(16) NULL AFTER enabled");
        $log[] = "🧩 Spalte 'source_type' wurde hinzugefügt.";
    }
    if (!isset($cols['source_id'])) {
        $db->exec("ALTER TABLE admin_buttons ADD COLUMN source_id VARCHAR(64) NULL AFTER source_type");
        $log[] = "🧩 Spalte 'source_id' wurde hinzugefügt.";
    }
    if (!isset($cols['last_seen'])) {
        $db->exec("ALTER TABLE admin_buttons ADD COLUMN last_seen TIMESTAMP NULL DEFAULT NULL AFTER source_id");
        $log[] = "🧩 Spalte 'last_seen' wurde hinzugefügt.";
    }
    if (!isset($cols['health'])) {
        $db->exec("ALTER TABLE admin_buttons ADD COLUMN health VARCHAR(8) NULL AFTER last_seen");
        $log[] = "🧩 Spalte 'health' wurde hinzugefügt.";
    }

    // Jetzt den Button anlegen
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
<title>Admin-Buttons Fix (Vollversion)</title>
<style>
body{background:#0e0e0e;color:#eee;font-family:ui-monospace,Consolas,monospace;padding:2rem}
.ok{color:#9fe3bd}.err{color:#f88}
</style>
</head>
<body>
<h2>Admin-Buttons Vollprüfung</h2>
<ul>
<?php foreach ($log as $l): ?>
  <li><?= htmlspecialchars($l, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
<?php endforeach; ?>
</ul>
<p><a href="/backend/">Zum Admin-Dashboard</a></p>
</body>
</html>
