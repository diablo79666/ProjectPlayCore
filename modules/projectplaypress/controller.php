<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/utils.php';
require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/loader.php';

ppc_security_headers();
ppc_require_login();

$db   = ppc_db();
$user = ppc_current_user() ?? '';

// Berechtigungsprüfung
function may_view(PDO $db, string $user): bool {
    if (function_exists('ppc_user_can') && ppc_user_can('view_admin')) return true;
    $st = $db->prepare("SELECT 1 FROM user_roles WHERE username=:u AND role='admin' LIMIT 1");
    $st->execute([':u'=>$user]); return (bool)$st->fetchColumn();
}

function may_manage(PDO $db, string $user): bool {
    if (function_exists('ppc_user_can') && ppc_user_can('manage_modules')) return true;
    $st = $db->prepare("SELECT 1 FROM user_roles WHERE username=:u AND role='admin' LIMIT 1");
    $st->execute([':u'=>$user]); return (bool)$st->fetchColumn();
}

if (!may_view($db, $user)) { 
    http_response_code(403); 
    echo "Zugriff verweigert (view_admin oder admin-Rolle erforderlich)."; 
    exit; 
}

$settings = ppress_settings_load();
$action   = strtolower((string)($_GET['action'] ?? 'about'));
$slug     = preg_replace('/[^a-z0-9\-]/i','-', (string)($_GET['slug'] ?? 'home'));

// CSRF
$csrf = 'ppress_csrf';
if (empty($_COOKIE[$csrf])) {
    $token = bin2hex(random_bytes(16));
    setcookie($csrf, $token, [
        'expires'=> time() + 3600, 'path'=>'/', 'secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'),
        'httponly'=> false, 'samesite'=> 'Lax',
    ]);
} else { 
    $token = (string)$_COOKIE[$csrf]; 
}

$msg = ''; 
$err = '';

// Standards beim ersten Erstellen
if ($action === 'about') {
    if (ppress_page_get('home') === '') {
        $demo = "<section class=\"hero\"><h1>Willkommen bei ProjectPlayCore</h1><p>Diese Startseite wird von <strong>ProjectPlayPress</strong> verwaltet. Bearbeite den Inhalt im Admin.</p></section>";
        @ppress_page_set('home', $demo);
    }
    if (ppress_page_get('maintenance') === '') {
        $demo = "<section class=\"hero\"><h1>Wartung</h1><p>Wir sind gleich wieder für dich da. Bitte später erneut versuchen.</p></section>";
        @ppress_page_set('maintenance', $demo);
    }
}

// POST: settings/save
try {
    if ($action === 'settings' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!may_manage($db, $user)) throw new RuntimeException('Rechte fehlen (manage_modules oder admin-Rolle).');
        $posted = (string)($_POST['ppress_csrf'] ?? '');
        if (!$posted || !$token || !hash_equals($token, $posted)) throw new RuntimeException('CSRF-Überprüfung fehlgeschlagen.');

        $maintenance = isset($_POST['maintenance']) && $_POST['maintenance'] === '1';
        $homeSlug = trim((string)($_POST['home_slug'] ?? 'home'));
        $homeSlug = preg_replace('/[^a-z0-9\-]/i','-', $homeSlug);
        if ($homeSlug === '') $homeSlug = 'home';

        $settings = ['maintenance'=>$maintenance,'home_slug'=>$homeSlug];
        if (!ppress_settings_save($settings)) throw new RuntimeException('Konnte Settings nicht speichern.');
        $msg = 'Einstellungen gespeichert.';
    }

    if ($action === 'edit' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!may_manage($db, $user)) throw new RuntimeException('Rechte fehlen (manage_modules oder admin-Rolle).');
        $posted = (string)($_POST['ppress_csrf'] ?? '');
        if (!$posted || !$token || !hash_equals($token, $posted)) throw new RuntimeException('CSRF-Überprüfung fehlgeschlagen.');

        $slug = preg_replace('/[^a-z0-9\-]/i','-', (string)($_POST['slug'] ?? 'home'));
        $html = (string)($_POST['html'] ?? '');
        if (!ppress_page_set($slug, $html)) throw new RuntimeException('Seite konnte nicht gespeichert werden.');
        $msg = "Seite „{$slug}“ gespeichert.";
    }
} catch (Throwable $t) { 
    $err = $t->getMessage(); 
}

$nav = '<p>'.
  '<a class="ppc-button" href="/backend/modules/projectplaypress/controller.php?action=settings">Einstellungen</a> '.
  '<a class="ppc-button" href="/backend/modules/projectplaypress/controller.php?action=edit&slug=home">Startseite bearbeiten</a> '.
  '<a class="ppc-button-secondary" href="/backend/modules/projectplaypress/controller.php?action=edit&slug=maintenance">Wartungsseite bearbeiten</a> '.
  '<a class="ppc-button-secondary" href="/backend/">Zurück zum Admin-Dashboard</a>'.
  '</p>';
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Module: Project PlayPress</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
 .wrap{max-width:1100px;margin:2rem auto;padding:1rem;}
 .kv{display:grid;grid-template-columns:220px 1fr;gap:.25rem .75rem}
 .kv div{padding:.2rem 0;border-bottom:1px dashed #333}
 textarea{width:100%;min-height:360px}
 .flash{margin:.5rem 0;padding:.6rem .8rem;border-radius:.6rem;}
 .flash.ok{background:#e7f7ee;border:1px solid #6cc799;}
 .flash.err{background:#fdecea;border:1px solid #f1998e;}
</style>
</head>
<body class="ppc-container">
<div class="wrap">
  <h1>Project PlayPress</h1>
  <?=$nav?>
  <?php if ($msg): ?><div class="flash ok"><?=h($msg)?></div><?php endif; ?>
  <?php if ($err): ?><div class="flash err"><?=h($err)?></div><?php endif; ?>

  <?php if ($action === 'about'): ?>
    <h2>About</h2>
    <div class="kv">
      <div>Wartungsmodus</div><div><?= $settings['maintenance'] ? 'aktiv' : 'inaktiv' ?></div>
      <div>Home-Slug</div><div><?= h($settings['home_slug']) ?></div>
      <div>Seitenordner</div><div><?= h(ppress_dir().'/pages') ?></div>
    </div>

  <?php elseif ($action === 'settings'): ?>
    <h2>Einstellungen</h2>
    <form method="post" action="/backend/modules/projectplaypress/controller.php?action=settings">
      <input type="hidden" name="ppress_csrf" value="<?=h($token)?>">
      <div class="kv">
        <div>Wartungsmodus</div>
        <div>
          <label><input type="checkbox" name="maintenance" value="1" <?= $settings['maintenance']?'checked':''; ?>> aktiv</label>
          <div class="ppc-muted">Admins können die Wartungsseite umgehen (Live-Preview möglich).</div>
        </div>
        <div>Home-Slug</div>
        <div>
          <input type="text" name="home_slug" value="<?=h($settings['home_slug'])?>" placeholder="home">
          <div class="ppc-muted">Welche Seite als Startseite gerendert wird (z. B. <code>home</code>).</div>
        </div>
      </div>
      <p style="margin-top:1rem">
        <button type="submit">Speichern</button>
        <a class="ppc-button-secondary" href="/frontend/home.php" target="_blank">Startseite öffnen</a>
      </p>
    </form>

  <?php elseif ($action === 'edit'): ?>
    <?php
      $content = ppress_page_get($slug);
      if ($content === '') {
        $content = "<section class=\"hero\"><h1>".h($slug)."</h1><p>Inhalt noch leer – hier HTML einfügen.</p></section>";
      }
    ?>
    <h2>Seite bearbeiten: „<?=h($slug)?>“</h2>
    <form method="post" action="/backend/modules/projectplaypress/controller.php?action=edit&slug=<?=h($slug)?>">
      <input type="hidden" name="ppress_csrf" value="<?=h($token)?>">
      <textarea name="html"><?=h($content)?></textarea>
      <p style="margin-top:1rem">
        <button type="submit">Speichern</button>
      </p>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
