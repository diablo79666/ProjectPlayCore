<?php
/**
 * ProjectPlayCore – Modul: identity
 * Admin-Panel für Provider-Einstellungen (DB-basiert)
 *
 * Zugriff erlaubt, wenn:
 *  - ppc_user_can('view_admin') ODER Rolle 'admin' ODER Rolle 'superadmin'
 */

declare(strict_types=1);

require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/utils.php';
require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../../../config.php';
@include_once __DIR__ . '/../roles/loader.php';

ppc_security_headers();
ppc_require_login();

$db   = ppc_db();
$user = (string)(ppc_current_user() ?? '');

$allowed = false;
try {
    if (function_exists('ppc_user_can') && ppc_user_can('view_admin')) $allowed = true;
    if (!$allowed && function_exists('ppc_has_role') && ppc_has_role('admin')) $allowed = true;
    if (!$allowed && function_exists('ppc_has_role') && ppc_has_role('superadmin')) $allowed = true;
} catch (Throwable $t) {}
if (!$allowed) { http_response_code(403); echo '403 – view_admin erforderlich'; exit; }

try {
    $db->exec("CREATE TABLE IF NOT EXISTS identity_settings (
        skey VARCHAR(64) PRIMARY KEY,
        svalue TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $t) {
    http_response_code(500); echo 'DB-Fehler (identity_settings): '.e($t->getMessage()); exit;
}

function idset_get(PDO $db, string $k, string $def=''): string {
    $st = $db->prepare("SELECT svalue FROM identity_settings WHERE skey=:k LIMIT 1");
    $st->execute([':k'=>$k]);
    $v = $st->fetchColumn();
    return ($v !== false) ? (string)$v : $def;
}
function idset_set(PDO $db, string $k, string $v): void {
    $st = $db->prepare("INSERT INTO identity_settings(skey,svalue) VALUES(:k,:v)
                        ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)");
    $st->execute([':k'=>$k, ':v'=>$v]);
}

$csrf_cookie='identity_admin_csrf';
$csrf = bin2hex(random_bytes(16));
setcookie($csrf_cookie,$csrf,[
  'expires'=>time()+3600,'path'=>'/','secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'),
  'httponly'=>false,'samesite'=>'Lax'
]);

$provider       = idset_get($db,'provider','demo');   // 'demo' | 'custom' | weitere
$enabled        = idset_get($db,'enabled','1');
$api_key        = idset_get($db,'api_key','');
$api_secret     = idset_get($db,'api_secret','');
$webhook_secret = idset_get($db,'webhook_secret','');
$redirect_base  = idset_get($db,'redirect_base','https://www.projectplaycore.de');

$note=''; $err='';
$action = strtolower((string)($_POST['action'] ?? $_GET['action'] ?? ''));
if ($action==='save') {
    try {
        $cookie = (string)($_COOKIE[$csrf_cookie] ?? '');
        $posted = (string)($_POST['csrf'] ?? '');
        if (!$cookie || !$posted || !hash_equals($cookie,$posted)) throw new RuntimeException('CSRF ungültig.');

        $provider
        // ...

        idset_set($db,'provider',$provider);
        idset_set($db,'enabled',$enabled);
        idset_set($db,'api_key',$api_key);
        idset_set($db,'api_secret',$api_secret);
        idset_set($db,'webhook_secret',$webhook_secret);
        idset_set($db,'redirect_base',$redirect_base);

        $note='Gespeichert.';
    } catch (Throwable $t) { $err=$t->getMessage(); }
}

$webhook_url  = rtrim($redirect_base ?: 'https://www.projectplaycore.de','/').'/backend/modules/identity/controller.php?action=kyc_webhook';
$callback_url = rtrim($redirect_base ?: 'https://www.projectplaycore.de','/').'/backend/modules/identity/controller.php?action=kyc_callback';
$start_url    = '/backend/modules/identity/controller.php?action=kyc_start';
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Identity – Provider-Einstellungen</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
.wrap{max-width:980px;margin:2rem auto;padding:1rem}
.card{border:1px solid #222;border-radius:12px;padding:14px;background:#0c0c0c;margin-bottom:12px}
label{display:block;margin:.5rem 0}
input[type=text],input[type=password],select{width:100%;padding:.45rem;border:1px solid #333;border-radius:8px;background:#111;color:#ddd}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.badge{display:inline-block;padding:.1rem .5rem;border:1px solid #555;border-radius:999px;font-size:.85rem}
.ok{color:#9fe3bd;border-color:#3da86b}
.err{color:#f3a1a1;border-color:#b55d5d}
small.mono{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;color:#9aa}
</style>
</head>
<body class="ppc-container">
<div class="wrap">
  <h1>Ausweisprüfung – Provider</h1>

  <?php if ($note): ?><div class="card"><span class="badge ok">OK</span> <?=e($note)?></div><?php endif; ?>
  <?php if ($err): ?><div class="card"><span class="badge err">Fehler</span> <?=e($err)?></div><?php endif; ?>

  <form method="post" class="card">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="csrf"   value="<?=e($csrf)?>">

    <div class="row">
      <label>Provider
        <select name="provider">
          <option value="demo"   <?= $provider==='demo'?'selected':'' ?>>Demo (intern)</option>
          <option value="custom" <?= $provider==='custom'?'selected':'' ?>>Externer Provider (manuell)</option>
        </select>
      </label>
      <label>Aktiviert
        <select name="enabled">
          <option value="1" <?= $enabled==='1'?'selected':'' ?>>Ja</option>
          <option value="0" <?= $enabled!=='1'?'selected':'' ?>>Nein</option>
        </select>
      </label>
    </div>

    <div class="row">
      <label>API-Key
        <input type="text" name="api_key" value="<?=e($api_key)?>" autocomplete="off" spellcheck="false">
      </label>
      <label>API-Secret
        <input type="password" name="api_secret" value="<?=e($api_secret)?>" autocomplete="off" spellcheck="false">
      </label>
    </div>

    <div class="row">
      <label>Webhook-Secret (Signaturprüfung)
        <input type="text" name="webhook_secret" value="<?=e($webhook_secret)?>" autocomplete="off" spellcheck="false">
      </label>
      <label>Basis-URL (Redirect-/Webhook-Domain)
        <input type="text" name="redirect_base" value="<?=e($redirect_base)?>" placeholder="https://www.projectplaycore.de">
      </label>
    </div>

    <div style="margin-top:.8rem;display:flex;gap:.6rem;flex-wrap:wrap">
      <button class="ppc-button" type="submit">Speichern</button>
      <a class="ppc-button-secondary" href="/backend/">Zum Admin-Dashboard</a>
    </div>
  </form>

  <div class="card">
    <h3>Technische Endpunkte</h3>
    <p><strong>Webhook-URL:</strong> <span class="mono"><?=e($webhook_url)?></span></p>
    <p><strong>Callback-URL:</strong> <span class="mono"><?=e($callback_url)?></span></p>
    <p><strong>Start (Test):</strong> <a class="ppc-button-small" href="<?=e($start_url)?>">Flow starten</a></p>
    <p><small class="mono">Hinweis: Bei Provider="demo" werden API-Key/Secret/Signatur nicht extern verwendet.</small></p>
  </div>
</div>
</body>
</html>
