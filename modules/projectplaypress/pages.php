<?php
/**
 * ProjectPlayPress – Admin: Seitenverwaltung
 * CRUD für ppp_pages inkl. Override-Pfad-Feld.
 *
 * Zugriff: manage_users ODER Rolle admin/superadmin ODER view_admin
 */

declare(strict_types=1);
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/utils.php';
require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../roles/loader.php';

ppc_security_headers();
ppc_require_login();

$db   = ppc_db();
$user = ppc_current_user() ?? '';

$allowed = false;
try {
    if (function_exists('ppc_user_can') && (ppc_user_can('manage_users') || ppc_user_can('view_admin'))) $allowed = true;
    if (!$allowed && function_exists('ppc_has_role') && (ppc_has_role('admin') || ppc_has_role('superadmin'))) $allowed = true;
} catch (Throwable $t) {}
if (!$allowed) { http_response_code(403); die('403 – Adminrechte erforderlich'); }

// CSRF
$csrfCookie = 'ppp_pages_csrf';
$csrf = bin2hex(random_bytes(16));
setcookie($csrfCookie, $csrf, [
  'expires'=>time()+3600,'path'=>'/','secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'),
  'httponly'=>false,'samesite'=>'Lax',
]);

// Table ensure (falls Migration noch nicht lief)
$db->exec("
    CREATE TABLE IF NOT EXISTS ppp_pages (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(190) NOT NULL UNIQUE,
        title VARCHAR(190) NOT NULL,
        status ENUM('draft','published') NOT NULL DEFAULT 'published',
        content MEDIUMTEXT NULL,
        template VARCHAR(190) NULL,
        override_path VARCHAR(255) NULL,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

function slug_valid(string $s): bool {
    if ($s === '') return true; // Home
    return (bool)preg_match('~^[a-z0-9\-\/]+$~', $s);
}

$action = strtolower((string)($_GET['action'] ?? $_POST['action'] ?? 'index'));
$msg=''; $err='';

try {
    if (in_array($action, ['create','update','delete'], true)) {
        $cookie = (string)($_COOKIE[$csrfCookie] ?? '');
        $posted = (string)($_POST['csrf'] ?? $_GET['csrf'] ?? '');
        if (!$cookie || !$posted || !hash_equals($cookie,$posted)) {
            throw new RuntimeException('CSRF ungültig.');
        }
    }

    if ($action === 'create') {
        $slug   = trim((string)($_POST['slug'] ?? ''));
        $title  = trim((string)($_POST['title'] ?? ''));
        $status = (($_POST['status'] ?? 'published') === 'draft') ? 'draft' : 'published';
        $content= (string)($_POST['content'] ?? '');
        $ovr    = trim((string)($_POST['override_path'] ?? ''));

        if (!slug_valid($slug)) throw new InvalidArgumentException('Slug ungültig. Erlaubt: a-z, 0-9, -, / (leer = Startseite).');
        if ($title === '') throw new InvalidArgumentException('Titel darf nicht leer sein.');

        $st = $db->prepare("INSERT INTO ppp_pages(slug,title,status,content,override_path) VALUES(:s,:t,:st,:c,:o)");
        $st->execute([':s'=>$slug, ':t'=>$title, ':st'=>$status, ':c'=>$content, ':o'=>$ovr]);
        $msg = 'Seite angelegt.';
        $action = 'index';
    } elseif ($action === 'update') {
        $id     = (int)($_POST['id'] ?? 0);
        $slug   = trim((string)($_POST['slug'] ?? ''));
        $title  = trim((string)($_POST['title'] ?? ''));
        $status = (($_POST['status'] ?? 'published') === 'draft') ? 'draft' : 'published';
        $content= (string)($_POST['content'] ?? '');
        $ovr    = trim((string)($_POST['override_path'] ?? ''));

        if ($id<=0) throw new InvalidArgumentException('ID fehlt.');
        if (!slug_valid($slug)) throw new InvalidArgumentException('Slug ungültig.');
        if ($title === '') throw new InvalidArgumentException('Titel darf nicht leer sein.');

        $st = $db->prepare("UPDATE ppp_pages SET slug=:s,title=:t,status=:st,content=:c,override_path=:o WHERE id=:id");
        $st->execute([':s'=>$slug, ':t'=>$title, ':st'=>$status, ':c'=>$content, ':o'=>$ovr, ':id'=>$id]);
        $msg = 'Seite aktualisiert.';
        $action = 'index';
    } elseif ($action === 'delete') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id<=0) throw new InvalidArgumentException('ID fehlt.');
        $db->prepare("DELETE FROM ppp_pages WHERE id=:id")->execute([':id'=>$id]);
        $msg = 'Seite gelöscht.';
        $action = 'index';
    }
} catch (Throwable $t) { $err = $t->getMessage(); }

// Views
function load_page(PDO $db, int $id): ?array {
    $st = $db->prepare("SELECT * FROM ppp_pages WHERE id=:id");
    $st->execute([':id'=>$id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}
$editId = (int)($_GET['id'] ?? 0);
$edit   = $editId>0 ? load_page($db, $editId) : null;

$rows = ($r=$db->query("SELECT * FROM ppp_pages ORDER BY (slug='') DESC, slug ASC")) ? ($r->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>ProjectPlayPress – Seiten</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
.wrap{max-width:1100px;margin:2rem auto;padding:1rem}
table{width:100%;border-collapse:collapse}th,td{padding:.5rem;border-bottom:1px solid #222;text-align:left}
.card{border:1px solid #222;border-radius:12px;padding:14px;background:#0c0c0c;margin:12px 0}
input[type=text], textarea, select{width:100%;padding:.45rem;border:1px solid #333;border-radius:8px;background:#111;color:#ddd}
textarea{min-height:160px}
.badge{display:inline-block;padding:.1rem .5rem;border:1px solid #555;border-radius:999px;font-size:.85rem}
.ok{color:#9fe3bd;border-color:#3da86b}
.err{color:#f3a1a1;border-color:#b55d5d}
.small{color:#9aa}
.actions a{margin-right:.6rem}
</style>
</head>
<body class="ppc-container">
<div class="wrap">
  <h1>ProjectPlayPress – Seitenverwaltung</h1>
  <?php if($msg):?><div class="card"><span class="badge ok">OK</span> <?=e($msg)?></div><?php endif;?>
  <?php if($err):?><div class="card"><span class="badge err">Fehler</span> <?=e($err)?></div><?php endif;?>

  <div class="card">
    <h3>Seiten</h3>
    <table>
      <thead><tr><th>Slug</th><th>Titel</th><th>Status</th><th>Override</th><th>Zuletzt</th><th>Aktionen</th></tr></thead>
      <tbody>
        <?php if(!$rows):?>
          <tr><td colspan="6"><em>Keine Seiten angelegt.</em></td></tr>
        <?php else: foreach($rows as $p): 
          $url = '/'.($p['slug']!=='' ? $p['slug'] : '');
        ?>
          <tr>
            <td><a href="<?=e($url)?>" target="_blank"><?=e($p['slug']===''?'/':$p['slug'])?></a></td>
            <td><?=e($p['title'])?></td>
            <td><?=e($p['status'])?></td>
            <td><span class="small"><?=e($p['override_path']?:'—')?></span></td>
            <td><span class="small"><?=e((string)$p['updated_at'])?></span></td>
            <td class="actions">
              <a class="ppc-button-small" href="/backend/modules/projectplaypress/pages.php?action=edit&id=<?=$p['id']?>">Bearbeiten</a>
              <a class="ppc-button-small" href="/backend/modules/projectplaypress/pages.php?action=delete&id=<?=$p['id']?>&csrf=<?=$csrf?>" onclick="return confirm('Wirklich löschen?')">Löschen</a>
            </td>
          </tr>
        <?php endforeach; endif;?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h3><?= $edit ? 'Seite bearbeiten' : 'Neue Seite anlegen' ?></h3>
    <form method="post" action="/backend/modules/projectplaypress/pages.php">
      <input type="hidden" name="csrf" value="<?=$csrf?>">
      <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
      <?php if($edit):?><input type="hidden" name="id" value="<?=$edit['id']?>"><?php endif;?>

      <label>Slug (leer = Startseite /)
        <input type="text" name="slug" value="<?=e($edit['slug'] ?? '')?>" placeholder="z.B. login oder profil">
      </label>
      <label>Titel
        <input type="text" name="title" value="<?=e($edit['title'] ?? '')?>" required>
      </label>
      <label>Status
        <select name="status">
          <option value="published" <?= (($edit['status'] ?? '')!=='draft')?'selected':''; ?>>published</option>
          <option value="draft" <?= (($edit['status'] ?? '')==='draft')?'selected':''; ?>>draft</option>
        </select>
      </label>
      <label>Override-Pfad (optional – Datei wird direkt included, falls vorhanden)
        <input type="text" name="override_path" value="<?=e($edit['override_path'] ?? '')?>" placeholder="/frontend/overrides/system/pages/MEINESEITE.php">
      </label>
      <label>Einfacher Inhalt (wird gezeigt, wenn kein Override vorhanden ist)
        <textarea name="content" placeholder="Einfacher HTML/Text-Inhalt..."><?=e($edit['content'] ?? '')?></textarea>
      </label>

      <div style="margin-top:.8rem;display:flex;gap:.6rem;flex-wrap:wrap">
        <button class="ppc-button" type="submit"><?= $edit ? 'Speichern' : 'Anlegen' ?></button>
        <a class="ppc-button-secondary" href="/backend/">Zum Admin-Dashboard</a>
      </div>
    </form>
  </div>

</div>
</body>
</html>
