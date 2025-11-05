<?php
/**
 * ProjectPlayCore – User-Dashboard
 * Zeigt nach Login einen direkten Button zum Admin-Dashboard,
 * falls der Nutzer die passenden Rechte hat.
 *
 * Rechteprüfung:
 *  - ppc_user_can('view_admin') ODER
 *  - Rolle 'admin' ODER
 *  - Rolle 'superadmin'
 */

declare(strict_types=1);

require_once __DIR__ . '/../backend/core/session.php';
require_once __DIR__ . '/../backend/core/security.php';
require_once __DIR__ . '/../backend/core/utils.php';
require_once __DIR__ . '/../backend/database/db.php';
require_once __DIR__ . '/../config.php';

// Rollen-/Cap-Helpers laden (falls Modul aktiv ist)
$rolesLoader = __DIR__ . '/../backend/modules/roles/loader.php';
if (is_file($rolesLoader)) {
    require_once $rolesLoader; // stellt ppc_user_can(), ppc_has_role() bereit
}

ppc_security_headers();
ppc_require_login();

$db   = ppc_db();
$user = (string)(ppc_current_user() ?? '');

// --- Admin-Rechte ermitteln -----------------------------------------------
$canSeeAdmin = false;
try {
    if (function_exists('ppc_user_can') && ppc_user_can('view_admin')) {
        $canSeeAdmin = true;
    } elseif (function_exists('ppc_has_role') && (ppc_has_role('admin') || ppc_has_role('superadmin'))) {
        $canSeeAdmin = true;
    } else {
        // Fallback: direkt in user_roles prüfen, falls roles-Modul (noch) nicht geladen ist
        $st = $db->prepare("SELECT 1 FROM user_roles WHERE username=:u AND role IN ('admin','superadmin') LIMIT 1");
        $st->execute([':u' => $user]);
        $canSeeAdmin = (bool)$st->fetchColumn();
    }
} catch (Throwable $t) {
    // auf Nummer sicher: kein 500, nur kein Button
    $canSeeAdmin = false;
}

// --- einfache UI -----------------------------------------------------------
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Dein Dashboard</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/assets/style.css">
<style>
  body.ppc-container{background:#0b0d10;color:#e7ecef}
  .wrap{max-width:960px;margin:2.2rem auto;padding:1rem}
  .card{border:1px solid #1c2128;background:#0f1318;border-radius:14px;padding:16px;margin-bottom:14px}
  .row{display:flex;gap:.6rem;flex-wrap:wrap}
  .ppc-button{display:inline-block}
  .meta{color:#9fb0bf;font-size:.95rem}
</style>
</head>
<body class="ppc-container">
<div class="wrap">
  <div class="card">
    <h1>Willkommen, <?= e($user) ?></h1>
    <p class="meta">Du bist eingeloggt. Hier findest du deine Schnellzugriffe.</p>
    <div class="row" style="margin-top:.6rem">
      <a class="ppc-button" href="/">Startseite</a>
      <a class="ppc-button-secondary" href="/user/profile.php">Mein Profil</a>
      <?php if ($canSeeAdmin): ?>
        <a class="ppc-button" href="/backend/">Admin-Dashboard</a>
      <?php endif; ?>
      <a class="ppc-button-secondary" href="/user/logout.php">Logout</a>
    </div>
  </div>

  <div class="card">
    <h2>Quick Infos</h2>
    <ul>
      <li>Admin-Button erscheint nur, wenn du <code>view_admin</code> hast oder Rolle <code>admin/superadmin</code>.</li>
      <li>Die Berechtigungen kannst du unter <em>Rollen &amp; Berechtigungen</em> verwalten.</li>
    </ul>
  </div>
</div>
</body>
</html>
