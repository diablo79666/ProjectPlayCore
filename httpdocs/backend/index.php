<?php
/**
 * ProjectPlayCore – Admin-Dashboard (stabile Button-Links, kein JS)
 * – Echte Buttons (Anchor mit Button-Klasse)
 * – Klare, absolute Ziele (keine Umleitungen)
 */

declare(strict_types=1);

require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/security.php';
require_once __DIR__ . '/core/utils.php';
require_once __DIR__ . '/database/db.php';
require_once __DIR__ . '/../config.php';

ppc_security_headers();
ppc_require_login();

$db   = ppc_db();
$user = (string)(ppc_current_user() ?? '');

// Rollen/Caps (falls Modul vorhanden)
$hasCaps = false;
try {
  $rolesLoader = __DIR__ . '/modules/roles/loader.php';
  if (is_file($rolesLoader)) { require_once $rolesLoader; $hasCaps = true; }
} catch (Throwable $t) {}

$canViewAdmin = false;
try {
  if ($hasCaps && function_exists('ppc_user_can') && ppc_user_can('view_admin')) $canViewAdmin = true;
  if (!$canViewAdmin && function_exists('ppc_has_role') && (ppc_has_role('admin') || ppc_has_role('superadmin'))) $canViewAdmin = true;
  if (!$canViewAdmin) {
    $st = $db->prepare("SELECT 1 FROM user_roles WHERE username=:u AND role IN ('admin','superadmin') LIMIT 1");
    $st->execute([':u'=>$user]);
    $canViewAdmin = (bool)$st->fetchColumn();
  }
} catch (Throwable $t) {}

if (!$canViewAdmin) { http_response_code(403); echo "403 – view_admin erforderlich"; exit; }

// ABSOLUTE, direkte Ziele
$L = [
  'modules_index'   => 'https://www.projectplaycore.de/backend/modules/index.php',
  'modules_sync'    => 'https://www.projectplaycore.de/backend/modules/sync.php',
  'roles'           => 'https://www.projectplaycore.de/backend/modules/roles/controller.php',
  'identity_admin'  => 'https://www.projectplaycore.de/backend/modules/identity/admin.php',
  'identity_ui'     => 'https://www.projectplaycore.de/backend/modules/identity/controller.php?action=index',
  'profiles'        => 'https://www.projectplaycore.de/backend/modules/profiles/controller.php?action=index',
  'pages'           => 'https://www.projectplaycore.de/backend/modules/projectplaypress/pages.php',
  'buttons'         => 'https://www.projectplaycore.de/backend/modules/uicore/buttons.php',
  'migrations'      => 'https://www.projectplaycore.de/backend/tools/run_migrations.php',
  'home'            => 'https://www.projectplaycore.de/',
  'user_dash'       => 'https://www.projectplaycore.de/user/dashboard.php',
];
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Admin-Dashboard – ProjectPlayCore</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/assets/style.css">
<style>
  body.ppc-container{background:#0b0d10;color:#e7ecef}
  .wrap{max-width:1100px;margin:2rem auto;padding:1rem}
  .card{border:1px solid #1c2128;background:#0f1318;border-radius:14px;padding:16px;margin-bottom:14px}
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px}
  .title{font-size:1.1rem;margin:0 0 .35rem 0}
  .muted{color:#94a3b8;font-size:.92rem}
  .row{display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.6rem}
  /* Buttons wirklich klickbar halten */
  a.ppc-button, a.ppc-button-secondary { display:inline-block; pointer-events:auto; }
</style>
</head>
<body class="ppc-container">
<div class="wrap">

  <div class="card">
    <h1 style="margin:0 0 .3rem 0">Admin-Dashboard</h1>
    <p class="muted">Angemeldet als <strong><?= e($user) ?></strong></p>
    <div class="row">
      <a class="ppc-button-secondary" href="<?= e($L['home']) ?>">Startseite</a>
      <a class="ppc-button-secondary" href="<?= e($L['user_dash']) ?>">User-Dashboard</a>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <div class="title">Module verwalten</div>
      <div class="muted">Module auflisten, aktivieren/deaktivieren</div>
      <div class="row">
        <a class="ppc-button" href="<?= e($L['modules_index']) ?>">Module öffnen</a>
        <a class="ppc-button-secondary" href="<?= e($L['modules_sync']) ?>">Module synchronisieren</a>
      </div>
    </div>

    <div class="card">
      <div class="title">Rollen & Berechtigungen</div>
      <div class="muted">Rollen zuweisen, Caps verwalten</div>
      <div class="row"><a class="ppc-button" href="<?= e($L['roles']) ?>">Öffnen</a></div>
    </div>

    <div class="card">
      <div class="title">Identity/KYC – Provider</div>
      <div class="muted">Provider wählen & KYC an/aus</div>
      <div class="row"><a class="ppc-button" href="<?= e($L['identity_admin']) ?>">Öffnen</a></div>
    </div>

    <div class="card">
      <div class="title">Identity/KYC – Status</div>
      <div class="muted">Ausweisprüfung starten/Status prüfen</div>
      <div class="row"><a class="ppc-button" href="<?= e($L['identity_ui']) ?>">Öffnen</a></div>
    </div>

    <div class="card">
      <div class="title">Minderjährigenprofile</div>
      <div class="muted">Kinder-Accounts & Policies</div>
      <div class="row"><a class="ppc-button" href="<?= e($L['profiles']) ?>">Öffnen</a></div>
    </div>

    <div class="card">
      <div class="title">Seiten (ProjectPlayPress)</div>
      <div class="muted">Seiten auflisten & bearbeiten</div>
      <div class="row"><a class="ppc-button" href="<?= e($L['pages']) ?>">Öffnen</a></div>
    </div>

    <div class="card">
      <div class="title">UI-Buttons / Navigation</div>
      <div class="muted">Buttons anlegen & verlinken</div>
      <div class="row"><a class="ppc-button" href="<?= e($L['buttons']) ?>">Öffnen</a></div>
    </div>

    <div class="card">
      <div class="title">Migrationen</div>
      <div class="muted">Schema aktualisieren</div>
      <div class="row"><a class="ppc-button" href="<?= e($L['migrations']) ?>">Ausführen</a></div>
    </div>
  </div>

</div>
</body>
</html>
