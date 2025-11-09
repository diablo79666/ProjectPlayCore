<?php
/**
 * PPC Tools – Module verwalten (Sync / Aktivieren / Deaktivieren)
 * Zugriff: view_admin ODER manage_modules ODER Rolle admin/superadmin
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../modules/loader.php';       // ppc_modules_*

ppc_security_headers();
ppc_require_login();

$db   = ppc_db();
$user = (string)(ppc_current_user() ?? '');

// Zugriff prüfen (eine der Bedingungen reicht)
$allowed = false;
try {
    if (function_exists('ppc_user_can') && ppc_user_can('view_admin')) $allowed = true;
    if (!$allowed && function_exists('ppc_user_can') && ppc_user_can('manage_modules')) $allowed = true;
    if (!$allowed) {
        // Fallback auf Rollen
        require_once __DIR__ . '/../modules/roles/loader.php';
        if (function_exists('ppc_has_role') && ppc_has_role('admin')) $allowed = true;
        if (!$allowed && function_exists('ppc_has_role') && ppc_has_role('superadmin')) $allowed = true;
    }
} catch (Throwable $t) { /* ignore */ }

if (!$allowed) {
    http_response_code(403);
    echo '403 – view_admin oder manage_modules erforderlich';
    exit;
}

// CSRF
$csrf_cookie = 'modules_mgr_csrf';
$csrf = bin2hex(random_bytes(16));
setcookie($csrf_cookie, $csrf, [
  'expires'=> time()+3600, 'path'=>'/',
  'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'),
  'httponly'=> false, 'samesite'=>'Lax'
]);

$msg=''; $err='';
$action = strtolower((string)($_GET['action'] ?? $_POST['action'] ?? ''));

try {
    if (in_array($action, ['sync','enable','disable'], true)) {
        $cookie = (string)($_COOKIE[$csrf_cookie] ?? '');
        $posted = (string)($_GET['csrf'] ?? $_POST['csrf'] ?? '');
        if (!$cookie || !$posted || !hash_equals($cookie, $posted)) {
            throw new RuntimeException('CSRF ungültig.');
        }
    }

    if ($action === 'sync') {
        $synced = ppc_modules_sync($db);
        $msg = 'Module synchronisiert: ' . ($synced ? implode(', ', $synced) : 'keine Änderungen');
    } elseif ($action === 'enable' || $action === 'disable') {
        $name = trim((string)($_GET['name'] ?? $_POST['name'] ?? ''));
        if ($name === '') throw new InvalidArgumentException('Modulname fehlt.');
        $ok = ppc_modules_set_enabled($db, $name, $action === 'enable');
        if ($ok) {
            $msg = "Modul „{$name}“ " . ($action==='enable' ? 'aktiviert' : 'deaktiviert') . '.';
        } else {
            $err = "Konnte Modul „{$name}“ nicht ändern.";
        }
    }
} catch (Throwable $t) {
    $err = $t->getMessage();
}

// Liste laden
$mods = ppc_modules_list($db);

// HTML
?><!doctype html><html lang="de"><head>
<meta charset="utf-8">
<title>Module verwalten</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
.wrap{max-width:1100px;margin:2rem auto;padding:1rem}
.card{border:1px solid #222;border-radius:12px;padding:14px;background:#0c0c0c;margin-bottom:12px}
table{width:100%;border-collapse:collapse}th,td{padding:.5rem;border-bottom:1px solid #222;text-align:left}
.badge{display:inline-block;padding:.1rem .5rem;border:1px solid #555;border-radius:999px;font-size:.85rem}
.ok{color:#9fe3bd;border-color:#3da86b}
.err{color:#f3a1a1;border-color:#b55d5d}
.mono{font-family:ui-monospace,SFMono-Regular,Consolas,monospace}
.actions a{margin-right:.4rem}
.topbar{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem}
</style>
</head>
<body class="ppc-container">
<div class="wrap">
  <h1>Module verwalten</h1>

  <div class="topbar">
    <a class="ppc-button" href="/backend/tools/modules_manager.php?action=sync&csrf=<?=e($csrf)?>">Module synchronisieren</a>
    <a class="ppc-button-secondary" href="/backend/">Zurück zum Admin-Dashboard</a>
  </div>

  <?php if ($msg): ?><div class="card"><span class="badge ok">OK</span> <?=e($msg)?></div><?php endif; ?>
  <?php if ($err): ?><div class="card"><span class="badge err">Fehler</span> <?=e($err)?></div><?php endif; ?>

  <div class="card">
    <?php if (!$mods): ?>
      <p><em>Keine Module gefunden. Klicke auf „Module synchronisieren“.</em></p>
    <?php else: ?>
      <table>
        <thead><tr><th>Name</th><th>Version</th><th>Status</th><th>Aktionen</th></tr></thead>
        <tbody>
        <?php foreach ($mods as $m): ?>
          <tr>
            <td><strong><?=e($m['name'])?></strong></td>
            <td class="mono"><?=e($m['version'])?></td>
            <td><?= ((int)$m['enabled']===1 ? 'Aktiv' : 'Inaktiv') ?></td>
            <td class="actions">
              <?php if ((int)$m['enabled']===1): ?>
                <a class="ppc-button-small" href="/backend/tools/modules_manager.php?action=disable&name=<?=urlencode($m['name'])?>&csrf=<?=e($csrf)?>">Deaktivieren</a>
              <?php else: ?>
                <a class="ppc-button-small" href="/backend/tools/modules_manager.php?action=enable&name=<?=urlencode($m['name'])?>&csrf=<?=e($csrf)?>">Aktivieren</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
