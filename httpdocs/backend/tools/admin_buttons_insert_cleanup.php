<?php
/**
 * ProjectPlayCore – Admin-Button-Setup: Container Health & Cleanup
 * Fügt den Cleanup-Button robust ein (kompatibel mit allen DB-Schemata)
 */

declare(strict_types=1);
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../../config.php';

ppc_security_headers();
ppc_require_login();

$db = ppc_db();

try {
    // Spalten prüfen
    $cols = [];
    $st = $db->query("SHOW COLUMNS FROM admin_buttons");
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $cols[strtolower($c['Field'])] = true;
    }

    // Spaltenname für Gruppe herausfinden
    $groupCol = isset($cols['group_name']) ? 'group_name' : (isset($cols['group']) ? 'group' : null);
    if (!$groupCol) {
        throw new RuntimeException("Keine Spalte für 'group_name' oder 'group' in admin_buttons gefunden.");
    }

    // Dynamisches SQL aufbauen
    $sql = "
        INSERT INTO admin_buttons (area, title, href, `$groupCol`, sort_order, required_cap, enabled, source_type, source_id)
        VALUES ('dashboard', 'Container Health & Cleanup', '/backend/tools/container_health_cleanup.php', 'System', 120, 'view_admin', 1, 'local', 'core')
        ON DUPLICATE KEY UPDATE title=VALUES(title), href=VALUES(href), enabled=1
    ";
    $db->exec($sql);

    echo '<h3 style="color:lime">✅ Button "Container Health & Cleanup" erfolgreich hinzugefügt.</h3>';
} catch (Throwable $t) {
    echo '<h3 style="color:red">❌ Fehler:</h3><pre>' . htmlspecialchars($t->getMessage(), ENT_QUOTES) . '</pre>';
}

echo '<p><a href="/backend/">Zum Admin-Dashboard</a></p>';
