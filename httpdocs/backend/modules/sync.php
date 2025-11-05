<?php
/**
 * Module synchronisieren – zeigt Ergebnis statt Redirect
 * Ruft ppc_modules_sync() und (falls vorhanden) ppc_migrations_run_for_enabled() auf.
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../../config.php';

// Loader mit Sync-/Migrations-Helfern
require_once __DIR__ . '/../modules/loader.php';
$runner = __DIR__ . '/../modules/migrations.php';
if (is_file($runner)) require_once $runner;

ppc_security_headers();
ppc_require_login();

$db = ppc_db();
$user = ppc_current_user() ?? '';

$errors = [];
$synced = [];
$migrated = false;

try {
  if (function_exists('ppc_modules_sync')) {
    $synced = ppc_modules_sync($db);
  } else {
    $errors[] = 'ppc_modules_sync() nicht verfügbar.';
  }
  if (function_exists('ppc_migrations_run_for_enabled')) {
    ppc_migrations_run_for_enabled();
    $migrated = true;
  }
} catch (Throwable $t) {
  $errors[] = $t->getMessage();
}

// aktuelle Liste laden
$list = [];
try {
  $st = $db->query("SELECT name, version, enabled FROM modules ORDER BY name");
  $list = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $t) {
  $errors[] = 'Liste konnte nicht geladen werden: '.$t->getMessage();
}
?>
<!doctype html>
<html lang="de"><head>
<meta charset="utf-8"><title>Module synchronisieren</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
.wrap{max-width:900px;margin:2rem auto;padding:1rem}
.card{border:1px solid #222;border-radius:12px;padding:14px;background:#0c0c0c;margin-bottom:12px}
ul{margin:.4rem 0 .2rem 1.2rem}
.table{width:100%;border-collapse:collapse}
th,td{padding:.5rem;border-bottom:1px solid #222;text-align:left}
.ok{color:#9fe3bd}
.err{color:#f3a1a1}
</style>
</head><body class="ppc-container">
<div class="wrap">
  <h1>Module synchronisieren</h1>

  <div class="card">
    <p><strong>Ergebnis:</strong></p>
    <ul>
      <li>Synced: <span class="ok"><?= e(implode(', ', $synced ?: [])) ?: '—' ?></span></li>
      <li>Migrationen: <?= $migrated ? '<span class="ok">ausgeführt</span>' : '<span class="err">nicht ausgeführt</span>' ?></li>
      <?php if ($errors): ?>
        <li class="err">Fehler: <?= e(implode(' | ', $errors)) ?></li>
      <?php endif; ?>
    </ul>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.6rem">
      <a class="ppc-button" href="/backend/modules/index.php">Zur Modul-Übersicht</a>
      <a class="ppc-button-secondary" href="/backend/">Zum Admin-Dashboard</a>
    </div>
  </div>

  <div class="card">
    <h3>Aktuelle Module</h3>
    <table class="table">
      <thead><tr><th>Name</th><th>Version</th><th>Aktiv</th></tr></thead>
      <tbody>
      <?php if (!$list): ?><tr><td colspan="3"><em>Keine Einträge gefunden.</em></td></tr>
      <?php else: foreach ($list as $m): ?>
        <tr>
          <td><?= e($m['name']) ?></td>
          <td><?= e($m['version']) ?></td>
          <td><?= ((int)$m['enabled']===1?'Ja':'Nein') ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body></html>
