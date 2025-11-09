<?php
// ============================================================================
// ProjectPlayCore – Dynamisches Admin-Dashboard
// Pfad: /backend/tools/dashboard_layout.php
// ============================================================================
declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../modules/loader.php';

ppc_security_headers();
ppc_require_login();

$db   = ppc_db();
$user = (string)(ppc_current_user() ?? '');

// ============================================================================
// ProjectPlayCore – Dashboard Widget: Migration Status
// Pfad: /backend/tools/dashboard_layout.php (Teilbereich)
// ============================================================================

$stateFile = (defined('PPC_STORAGE') ? PPC_STORAGE : (__DIR__ . '/../../storage')) . '/migrations_state.json';
$state = is_file($stateFile) ? json_decode(@file_get_contents($stateFile) ?: '[]', true) : [];

$moduleCount = count($state);
$migrationCount = 0;
foreach ($state as $mod => $entries) {
    $migrationCount += count($entries);
}
$lastChange = is_file($stateFile) ? date('d.m.Y H:i:s', filemtime($stateFile)) : null;

// Statusfarbe und Text wählen
if ($moduleCount === 0) {
    $statusClass = 'err';
    $statusText = 'Keine Migrationen gefunden';
} elseif ($migrationCount > 0) {
    $statusClass = 'ok';
    $statusText = "{$migrationCount} Migrationen in {$moduleCount} Modulen";
} else {
    $statusClass = 'warn';
    $statusText = 'Noch keine Migrationen ausgeführt';
}
?>

<div class="card">
  <h3>Migration Status</h3>
  <p class="<?= $statusClass ?>"><?= e($statusText) ?></p>
  <?php if ($lastChange): ?>
    <p><small>Letzte Änderung: <?= e($lastChange) ?></small></p>
  <?php endif; ?>
  <div style="margin-top:.5rem">
    <a class="ppc-button" href="/backend/tools/migrations_status.php">Details ansehen</a>
    <a class="ppc-button-secondary" href="/backend/tools/run_migrations.php">Migrationen ausführen</a>
  </div>
</div>
// ---------------------------------------------------------------------------
// Buttons aus DB laden
// ---------------------------------------------------------------------------
try {
    $stmt = $db->query("SELECT * FROM admin_buttons WHERE enabled=1 ORDER BY btn_group, sort_order, title");
    $buttons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $t) {
    http_response_code(500);
    echo "Fehler beim Laden der Buttons: " . htmlspecialchars($t->getMessage());
    exit;
}

// Nach Gruppen sortieren
$groups = [];
foreach ($buttons as $btn) {
    $g = $btn['btn_group'] ?: 'Sonstiges';
    $groups[$g][] = $btn;
}

// ---------------------------------------------------------------------------
// HTML-Ausgabe
// ---------------------------------------------------------------------------
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Admin-Dashboard – ProjectPlayCore</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/assets/style.css">
<style>
body.ppc-container { background:#0b0d10; color:#e7ecef; }
.wrap { max-width:1200px; margin:2rem auto; padding:1rem; }
.card { background:#11151a; border:1px solid #1e252c; border-radius:12px; padding:16px; margin-bottom:16px; }
h1 { margin-top:0; color:#9fe3bd; }
.group-title { margin:1.5rem 0 0.5rem 0; font-size:1.2rem; color:#a7b8c8; border-bottom:1px solid #222; padding-bottom:4px; }
.btn-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:.6rem; margin-top:.6rem; }
.ppc-button { display:block; text-align:center; padding:.6rem .8rem; background:#1e252c; border-radius:8px; color:#e7ecef; text-decoration:none; border:1px solid #2d343d; transition:all .2s; }
.ppc-button:hover { background:#27313c; transform:translateY(-1px); }
.footer { margin-top:2rem; font-size:.9rem; text-align:center; color:#8b9aa7; }
</style>
</head>
<body class="ppc-container">
<div class="wrap">
  <div class="card">
    <h1>Admin-Dashboard</h1>
    <p class="muted">Angemeldet als <strong><?= e($user) ?></strong></p>
  </div>

  <?php if (empty($groups)): ?>
    <div class="card">Keine Buttons registriert.</div>
  <?php else: ?>
    <?php foreach ($groups as $groupName => $list): ?>
      <div class="card">
        <div class="group-title"><?= e($groupName) ?></div>
        <div class="btn-grid">
          <?php foreach ($list as $btn): ?>
            <a class="ppc-button" href="<?= e($btn['href']) ?>">
              <?= e($btn['title']) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="footer">ProjectPlayCore Admin-Dashboard</div>
</div>
</body>
</html>
