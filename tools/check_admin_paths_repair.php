<?php
// ============================================================================
// ProjectPlayCore – Admin-Button Path Auto-Repair (mit Datei-Mapping)
// ============================================================================
declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../../config.php';

ppc_security_headers();
ppc_require_login();

$db = ppc_db();
$user = (string)(ppc_current_user() ?? 'Unbekannt');

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// -----------------------------------------------------------------------------
// Manuelles Mapping: Buttontitel → tatsächlicher Dateiname im /backend/tools/
// -----------------------------------------------------------------------------
$mapping = [
    'Admin Buttons Übersicht'            => 'admin_buttons.php',
    'Admin Buttons – Vollständige Reparatur' => 'admin_buttons_fix_full.php',
    'Admin Buttons – Gruppen-Spalten Fix'    => 'admin_buttons_fix_group_column.php',
    'Admin Buttons Cleanup'              => 'admin_buttons_insert_cleanup.php',
    'Container Synchronisation'          => 'containers_sync.php',
    'Dashboard Layout Verwaltung'        => 'dashboard_layout.php',
    'Datenbank-Inspektor'                => 'db_inspector.php',
    'Migrationstabelle Reparieren'       => 'fix_migrations_table.php',
    'Migration Runner'                   => 'run_migrations.php',
    'Container Health & Cleanup'         => 'container_health_cleanup.php',
];

$changed = [];
$skipped = [];
$toolDir = $_SERVER['DOCUMENT_ROOT'] . '/backend/tools/';

$buttons = $db->query("SELECT id, title, path FROM admin_buttons ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

foreach ($buttons as $b) {
    $title = trim($b['title']);
    if (!$title || !isset($mapping[$title])) {
        $skipped[] = $title;
        continue;
    }
    $file = $mapping[$title];
    $full = $toolDir . $file;
    if (file_exists($full)) {
        $rel = '/backend/tools/' . $file;
        $stmt = $db->prepare("UPDATE admin_buttons SET path = :p WHERE id = :id");
        $stmt->execute([':p' => $rel, ':id' => $b['id']]);
        $changed[] = [$title, $rel];
    } else {
        $skipped[] = $title . " (Datei fehlt: $file)";
    }
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Admin-Button Auto-Reparatur (Mapping)</title>
<style>
body{background:#0b0d10;color:#e7ecef;font-family:system-ui,sans-serif;padding:2rem}
.ok{color:#9fe3bd} .err{color:#f8b4b4}
a{color:#9fe3bd;text-decoration:none} a:hover{text-decoration:underline}
</style>
</head>
<body>
<h1>Admin-Button Auto-Reparatur</h1>
<p>Angemeldet als <strong><?=e($user)?></strong></p>
<?php if ($changed): ?>
  <h2 class="ok">✅ Erfolgreich korrigiert</h2>
  <ul><?php foreach ($changed as $c): ?><li><?=e($c[0])?> → <code><?=e($c[1])?></code></li><?php endforeach; ?></ul>
<?php endif; ?>

<?php if ($skipped): ?>
  <h2 class="err">⚠️ Übersprungen</h2>
  <ul><?php foreach ($skipped as $s): ?><li><?=e($s)?></li><?php endforeach; ?></ul>
<?php endif; ?>

<p><a href="/backend/tools/check_admin_paths.php">Zurück zur Pfadprüfung</a> |
<a href="/backend/">Zum Admin-Dashboard</a></p>
</body>
</html>
