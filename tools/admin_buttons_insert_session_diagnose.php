<?php
// ============================================================================
// ProjectPlayCore – Admin-Button-Setup: Session Diagnose
// Pfad: /backend/tools/admin_buttons_insert_session_diagnose.php
// Zweck: Fügt den Diagnose-Button robust ein (prüft Duplikate nach Titel & Pfad)
// ============================================================================
declare(strict_types=1);
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../../config.php';

ppc_security_headers();
ppc_require_login();

$db = ppc_db();

try {
    // ------------------------------------------------------------------------
    // Spalten prüfen
    // ------------------------------------------------------------------------
    $cols = [];
    $st = $db->query("SHOW COLUMNS FROM admin_buttons");
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $cols[strtolower($c['Field'])] = true;
    }
    $groupCol = isset($cols['group_name']) ? 'group_name' : (isset($cols['group']) ? 'group' : null);
    if (!$groupCol) throw new RuntimeException("Spalte 'group' oder 'group_name' nicht gefunden.");

    // ------------------------------------------------------------------------
    // Zielwerte
    // ------------------------------------------------------------------------
    $title  = 'Session Diagnose';
    $href   = '/backend/tools/session_diagnose.php';
    $area   = 'dashboard';
    $group  = 'System';
    $sort   = 130;
    $cap    = 'view_admin';
    $srcTyp = 'local';
    $srcId  = 'core';

    // ------------------------------------------------------------------------
    // Duplikate prüfen
    // ------------------------------------------------------------------------
    $check = $db->prepare("SELECT id FROM admin_buttons WHERE title=:t OR href=:h LIMIT 1");
    $check->execute([':t' => $title, ':h' => $href]);
    $existingId = $check->fetchColumn();

    if ($existingId) {
        $upd = $db->prepare("
            UPDATE admin_buttons
            SET area=:a, `$groupCol`=:g, sort_order=:s, required_cap=:c,
                enabled=1, source_type=:st, source_id=:si
            WHERE id=:id
        ");
        $upd->execute([
            ':a' => $area,
            ':g' => $group,
            ':s' => $sort,
            ':c' => $cap,
            ':st' => $srcTyp,
            ':si' => $srcId,
            ':id' => $existingId,
        ]);
        $msg = "♻️ Bestehender Button 'Session Diagnose' wurde aktualisiert (ID $existingId).";
    } else {
        $ins = $db->prepare("
            INSERT INTO admin_buttons (area, title, href, `$groupCol`, sort_order, required_cap, enabled, source_type, source_id)
            VALUES (:a, :t, :h, :g, :s, :c, 1, :st, :si)
        ");
        $ins->execute([
            ':a' => $area,
            ':t' => $title,
            ':h' => $href,
            ':g' => $group,
            ':s' => $sort,
            ':c' => $cap,
            ':st' => $srcTyp,
            ':si' => $srcId,
        ]);
        $msg = "✅ Neuer Button 'Session Diagnose' erfolgreich hinzugefügt.";
    }

    // ------------------------------------------------------------------------
    // Doppelte Einträge entfernen
    // ------------------------------------------------------------------------
    $cleanup = $db->prepare("
        DELETE FROM admin_buttons
        WHERE (title=:t OR href=:h)
        AND id NOT IN (
            SELECT MIN(id) FROM (SELECT id FROM admin_buttons WHERE title=:t OR href=:h) AS tmp
        )
    ");
    $cleanup->execute([':t' => $title, ':h' => $href]);

    echo '<h3 style="color:lime">' . htmlspecialchars($msg) . '</h3>';
    echo '<p style="color:gray">🧹 Doppelte oder veraltete Einträge wurden automatisch entfernt.</p>';

} catch (Throwable $t) {
    echo '<h3 style="color:red">❌ Fehler:</h3><pre>' . htmlspecialchars($t->getMessage(), ENT_QUOTES) . '</pre>';
}

echo '<p><a href="/backend/">⬅️ Zurück zum Admin-Dashboard</a></p>';
