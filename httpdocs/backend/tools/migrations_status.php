<?php
/**
 * PPC Tools – migrations_status
 * Zeigt: ppc_migrations-Einträge, Tabellen/Spalten-Checks und aktivierte Module.
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../modules/nav.php'; // triggert Auto-Migration

ppc_security_headers();
ppc_require_login();

$db = ppc_db();
$user = ppc_current_user();

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function hasTable(PDO $db, string $table): bool {
  try {
    $st=$db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t");
    $st->execute([':t'=>$table]);
    return (bool)$st->fetchColumn();
  } catch(Throwable $t){ return false; }
}
function hasColumn(PDO $db, string $table, string $col): bool {
  try {
    $st=$db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c");
    $st->execute([':t'=>$table, ':c'=>$col]);
    return (bool)$st->fetchColumn();
  } catch(Throwable $t){ return false; }
}

$mods = [];
try {
  $mods = $db->query("SELECT name,version,enabled FROM modules ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $t) {}

$migs = [];
try {
  if (hasTable($db, 'ppc_migrations')) {
    $migs = $db->query("SELECT module,version,checksum,applied_at FROM ppc_migrations ORDER BY module,version")->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
} catch (Throwable $t) {}

$checks = [
  'parents' => hasTable($db,'parents'),
  'children' => hasTable($db,'children'),
  'children.child_email' => hasColumn($db,'children','child_email'),
  'child_policies' => hasTable($db,'child_policies'),
  'child_audit' => hasTable($db,'child_audit'),
];

?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>PPC Tools – migrations_status</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
.wrap{max-width:1000px;margin:2rem auto;padding:1rem}
.card{border:1px solid #222;border-radius:12px;padding:14px;background:#0c0c0c;margin-bottom:12px}
.badge{display:inline-block;padding:.15rem .5rem;border:1px solid #555;border-radius:999px;font-size:.85rem;margin-left:.5rem}
.ok{color:#8f8}
.err{color:#f99}
table{width:100%;border-collapse:collapse}
th,td{padding:.5rem;border-bottom:1px solid #222;text-align:left}
small{color:#999}
.rowbtns{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.5rem}
</style>
</head>
<body class="ppc-container">
<div class="wrap">
  <div class="card">
    <h2>Status</h2>
    <p>Angemeldet als: <strong><?=e($user)?></strong></p>
    <div class="rowbtns">
      <a class="ppc-button" href="/backend/tools/whoami.php">whoami</a>
      <a class="ppc-button" href="/backend/">Admin-Dashboard</a>
      <a class="ppc-button" href="/user/dashboard.php">User-Dashboard</a>
      <a class="ppc-button" href="/backend/modules/profiles/controller.php?action=index">Kinderprofile</a>
    </div>
  </div>

  <div class="card">
    <h3>Module (DB)</h3>
    <?php if (!$mods): ?>
      <p class="err">Keine Einträge in <code>modules</code> gefunden. Bitte im Admin-Dashboard „Module synchronisieren“ ausführen.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Name</th><th>Version</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($mods as $m): ?>
            <tr>
              <td><?=e($m['name'])?></td>
              <td><?=e((string)$m['version'])?></td>
              <td><?=((int)$m['enabled']===1?'<span class="ok">aktiv</span>':'<span class="err">inaktiv</span>')?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p><small>Wichtig für Navigation: <strong>uicore</strong> und <strong>profiles</strong> sollten „aktiv“ sein.</small></p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>Migrationen</h3>
    <?php if (!hasTable($db,'ppc_migrations')): ?>
      <p class="err">Tabelle <code>ppc_migrations</code> fehlt → Auto-Migration noch nicht gelaufen.</p>
    <?php elseif (!$migs): ?>
      <p class="err">Noch keine Migrationen registriert. Seite ggf. erneut laden, 5-Min-Lock beachten.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Modul</th><th>Version</th><th>Checksum</th><th>Applied</th></tr></thead>
        <tbody>
          <?php foreach ($migs as $g): ?>
            <tr>
              <td><?=e($g['module'])?></td>
              <td><?=e($g['version'])?></td>
              <td><small><?=e($g['checksum'])?></small></td>
              <td><?=e($g['applied_at'])?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>Tabellen/Spalten (profiles)</h3>
    <ul>
      <?php foreach ($checks as $k=>$v): ?>
        <li><?=e($k)?>: <?= $v ? '<span class="ok">OK</span>' : '<span class="err">FEHLT</span>' ?></li>
      <?php endforeach; ?>
    </ul>
    <p><small>Falls hier etwas fehlt: Seite 1–2× neu laden (Lock 5 Min). Ansonsten im Admin „Module synchronisieren“ und sicherstellen, dass <code>profiles</code> aktiv ist.</small></p>
  </div>
</div>
</body>
</html>
