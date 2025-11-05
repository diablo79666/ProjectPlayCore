<?php
/**
 * Dashboard-Layout-Editor (Page Editor v1)
 * - Zonen: header | main | sidebar | footer
 * - Buttons (admin_buttons) per UI zwischen Zonen verschieben, sortieren, (de)aktivieren
 * - Optional: neue Buttons anlegen (quick add)
 *
 * Zugriff: view_admin (oder DB-Rolle admin/superadmin als Fallback)
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../roles/loader.php'; // ppc_user_can/ppc_has_role falls vorhanden
require_once __DIR__ . '/../../config.php';

ppc_security_headers();
ppc_require_login();

$db   = ppc_db();
$user = (string)(ppc_current_user() ?? '');

// --- Access -----------------------------------------------------------------
$allowed = false;
if (function_exists('ppc_user_can') && ppc_user_can('view_admin')) $allowed = true;
if (!$allowed && function_exists('ppc_has_role') && ppc_has_role('admin')) $allowed = true;
if (!$allowed && function_exists('ppc_has_role') && ppc_has_role('superadmin')) $allowed = true;
if (!$allowed) { http_response_code(403); echo '403 – view_admin erforderlich'; exit; }

// --- Schema-Sicherung -------------------------------------------------------
$db->exec("
CREATE TABLE IF NOT EXISTS admin_buttons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  area VARCHAR(64) NOT NULL,                  -- z. B. 'dashboard', 'navbar', 'footer'
  section VARCHAR(32) NULL,                   -- neu: 'header'|'main'|'sidebar'|'footer'|NULL
  title VARCHAR(140) NOT NULL,
  href  VARCHAR(255) NOT NULL,
  icon  VARCHAR(64)  NULL,
  required_cap VARCHAR(64) NULL,
  sort_order INT NOT NULL DEFAULT 100,
  status TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

# Spalte section idempotent hinzufügen
$hasSection = false;
try {
  $st = $db->query("SELECT 1 FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_buttons' AND COLUMN_NAME='section' LIMIT 1");
  $hasSection = (bool)$st->fetchColumn();
} catch (Throwable $t) {}
if (!$hasSection) {
  try { $db->exec("ALTER TABLE admin_buttons ADD COLUMN section VARCHAR(32) NULL AFTER area"); } catch(Throwable $t) {}
}

// --- CSRF -------------------------------------------------------------------
$csrf_cookie = 'dashlayout_csrf';
$csrf = bin2hex(random_bytes(16));
setcookie($csrf_cookie, $csrf, [
  'expires'=>time()+3600,'path'=>'/','secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'),
  'httponly'=>false,'samesite'=>'Lax',
]);

// --- Actions ----------------------------------------------------------------
$note=''; $err='';
$action = strtolower((string)($_POST['action'] ?? $_GET['action'] ?? ''));

try {
  if ($action === 'move' || $action === 'sort' || $action === 'toggle' || $action === 'delete' || $action === 'quick_add') {
    $cookie = (string)($_COOKIE[$csrf_cookie] ?? '');
    $posted = (string)($_POST['csrf'] ?? '');
    if (!$cookie || !$posted || !hash_equals($cookie,$posted)) throw new RuntimeException('CSRF ungültig.');

    if ($action === 'quick_add') {
      $title = trim((string)($_POST['title'] ?? ''));
      $href  = trim((string)($_POST['href']  ?? ''));
      $icon  = trim((string)($_POST['icon']  ?? ''));
      $cap   = trim((string)($_POST['cap']   ?? ''));
      $section = trim((string)($_POST['section'] ?? 'main'));
      if ($title==='' || $href==='') throw new InvalidArgumentException('Titel und Link sind Pflicht.');
      $st = $db->prepare("INSERT INTO admin_buttons(area, section, title, href, icon, required_cap, sort_order, status)
                          VALUES('dashboard', :section, :title, :href, :icon, :cap, 100, 1)");
      $st->execute([':section'=>$section, ':title'=>$title, ':href'=>$href, ':icon'=>$icon, ':cap'=>$cap ?: null]);
      $note = 'Button angelegt.';
    }

    if (in_array($action, ['move','sort','toggle','delete'], true)) {
      $id = (int)($_POST['id'] ?? 0);
      if ($id<=0) throw new InvalidArgumentException('Ungültige ID.');
      if ($action==='move') {
        $section = trim((string)($_POST['section'] ?? 'main'));
        if (!in_array($section, ['header','main','sidebar','footer'], true)) $section = 'main';
        $st = $db->prepare("UPDATE admin_buttons SET section=:s WHERE id=:id");
        $st->execute([':s'=>$section, ':id'=>$id]);
        $note='Verschoben.';
      } elseif ($action==='sort') {
        $dir = (string)($_POST['dir'] ?? 'up');
        // Nachbarn suchen innerhalb gleicher section
        $st = $db->prepare("SELECT section, sort_order FROM admin_buttons WHERE id=:id AND area='dashboard' LIMIT 1");
        $st->execute([':id'=>$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $sec = (string)($row['section'] ?? 'main');
        $order = (int)($row['sort_order'] ?? 100);

        if ($dir === 'up') {
          $nb = $db->prepare("SELECT id,sort_order FROM admin_buttons
                              WHERE area='dashboard' AND section <=> :sec AND sort_order < :o
                              ORDER BY sort_order DESC, id DESC LIMIT 1");
          $nb->execute([':sec'=>$sec, ':o'=>$order]);
        } else {
          $nb = $db->prepare("SELECT id,sort_order FROM admin_buttons
                              WHERE area='dashboard' AND section <=> :sec AND sort_order > :o
                              ORDER BY sort_order ASC, id ASC LIMIT 1");
          $nb->execute([':sec'=>$sec, ':o'=>$order]);
        }
        $n = $nb->fetch(PDO::FETCH_ASSOC);
        if ($n) {
          $db->beginTransaction();
          $db->prepare("UPDATE admin_buttons SET sort_order=:o2 WHERE id=:id1")->execute([':o2'=>$n['sort_order'], ':id1'=>$id]);
          $db->prepare("UPDATE admin_buttons SET sort_order=:o1 WHERE id=:id2")->execute([':o1'=>$order, ':id2'=>$n['id']]);
          $db->commit();
          $note='Reihenfolge geändert.';
        }
      } elseif ($action==='toggle') {
        $st = $db->prepare("UPDATE admin_buttons SET status = 1 - status WHERE id=:id");
        $st->execute([':id'=>$id]);
        $note='Status geändert.';
      } elseif ($action==='delete') {
        $st = $db->prepare("DELETE FROM admin_buttons WHERE id=:id");
        $st->execute([':id'=>$id]);
        $note='Button gelöscht.';
      }
    }
  }
} catch (Throwable $t) {
  $err = $t->getMessage();
}

// --- Daten laden ------------------------------------------------------------
$btns = [];
$st = $db->prepare("SELECT * FROM admin_buttons WHERE area='dashboard' ORDER BY section, sort_order, id");
$st->execute();
while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
  $sec = (string)($r['section'] ?? 'main');
  if ($sec==='') $sec='main';
  $btns[$sec][] = $r;
}
foreach (['header','main','sidebar','footer'] as $s) if (!isset($btns[$s])) $btns[$s]=[];

function renderItem(array $b, string $csrf): string {
  $id = (int)$b['id'];
  $cap = (string)($b['required_cap'] ?? '');
  $status = (int)$b['status'] === 1 ? 'Aktiv' : 'Inaktiv';
  $secSel = function(string $s) use($b){ return ((string)$b['section'] ?? 'main')===$s ? 'selected':''; };
  $icon = e((string)($b['icon'] ?? ''));
  return '
  <div class="tile">
    <div class="meta">
      <div><strong>'.e($b['title']).'</strong></div>
      <div class="mono">'.e($b['href']).'</div>
      <div class="dim">Cap: '.($cap!==''?e($cap):'—').' · Status: '.$status.' · Sort: '.(int)$b['sort_order'].'</div>
    </div>
    <div class="ctrls">
      <form method="post" class="inline">
        <input type="hidden" name="csrf" value="'.e($csrf).'">
        <input type="hidden" name="action" value="sort">
        <input type="hidden" name="id" value="'.$id.'">
        <button class="btn" name="dir" value="up"    title="nach oben">↑</button>
        <button class="btn" name="dir" value="down"  title="nach unten">↓</button>
      </form>
      <form method="post" class="inline">
        <input type="hidden" name="csrf" value="'.e($csrf).'">
        <input type="hidden" name="action" value="move">
        <input type="hidden" name="id" value="'.$id.'">
        <select name="section" class="sel" onchange="this.form.submit()">
          <option '.$secSel('header').' value="header">header</option>
          <option '.$secSel('main').'   value="main">main</option>
          <option '.$secSel('sidebar').'value="sidebar">sidebar</option>
          <option '.$secSel('footer').' value="footer">footer</option>
        </select>
      </form>
      <form method="post" class="inline">
        <input type="hidden" name="csrf" value="'.e($csrf).'">
        <input type="hidden" name="action" value="toggle">
        <input type="hidden" name="id" value="'.$id.'">
        <button class="btn" title="(de)aktivieren">◼</button>
      </form>
      <form method="post" class="inline" onsubmit="return confirm(\'Wirklich löschen?\')">
        <input type="hidden" name="csrf" value="'.e($csrf).'">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="'.$id.'">
        <button class="btn danger" title="löschen">✖</button>
      </form>
    </div>
  </div>';
}

?><!doctype html>
<html lang="de"><head>
<meta charset="utf-8">
<title>Dashboard-Layout</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
.wrap{max-width:1200px;margin:2rem auto;padding:1rem}
.hrow{display:flex;align-items:center;justify-content:space-between;gap:.5rem}
.grid{display:grid;grid-template-columns:2fr 3fr 1.6fr;gap:12px}
.card{border:1px solid #222;border-radius:12px;padding:14px;background:#0c0c0c}
h2{margin:.2rem 0 1rem}
.zone{min-height:80px}
.tile{border:1px solid #333;border-radius:10px;padding:.6rem .7rem;margin:.4rem 0;background:#0e0e0e;display:flex;gap:.8rem;justify-content:space-between}
.meta .mono{font-family:ui-monospace,Consolas,monospace;color:#aaa}
.meta .dim{color:#9aa;font-size:.9rem}
.inline{display:inline}
.btn{border:1px solid #444;background:#151515;color:#ddd;border-radius:8px;padding:.2rem .5rem;margin:0 .15rem;cursor:pointer}
.btn:hover{background:#1a1a1a}
.btn.danger{border-color:#a44;color:#f3a1a1}
.sel{border:1px solid #333;background:#101010;color:#ddd;border-radius:8px;padding:.2rem .35rem}
.input{width:100%;padding:.45rem;border:1px solid #333;border-radius:8px;background:#111;color:#ddd}
.row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.badge{display:inline-block;padding:.15rem .5rem;border:1px solid #555;border-radius:999px}
.ok{border-color:#3da86b;color:#9fe3bd}.err{border-color:#b55d5d;color:#f3a1a1}
.hint{color:#9aa}
</style>
</head>
<body class="ppc-container">
<div class="wrap">
  <div class="hrow">
    <h1>Dashboard-Layout</h1>
    <div><a class="ppc-button-secondary" href="/backend/">Zurück zum Admin-Dashboard</a></div>
  </div>

  <?php if ($note): ?><div class="card"><span class="badge ok">OK</span> <?=e($note)?></div><?php endif; ?>
  <?php if ($err): ?><div class="card"><span class="badge err">Fehler</span> <?=e($err)?></div><?php endif; ?>

  <div class="card hint">
    <strong>Hinweis:</strong> Das Admin-Dashboard zeigt nur Buttons, die <em>aktiv</em> sind und deren <em>required_cap</em> du besitzt.  
    Bereiche/Zonen: <code>header</code>, <code>main</code>, <code>sidebar</code>, <code>footer</code>.
  </div>

  <div class="grid">
    <div class="card zone">
      <h2>header</h2>
      <?php foreach ($btns['header'] as $b) echo renderItem($b, $csrf); ?>
    </div>
    <div class="card zone">
      <h2>main</h2>
      <?php foreach ($btns['main'] as $b) echo renderItem($b, $csrf); ?>
    </div>
    <div class="card zone">
      <h2>sidebar</h2>
      <?php foreach ($btns['sidebar'] as $b) echo renderItem($b, $csrf); ?>
    </div>
  </div>

  <div class="card zone" style="margin-top:12px">
    <h2>footer</h2>
    <?php foreach ($btns['footer'] as $b) echo renderItem($b, $csrf); ?>
  </div>

  <div class="card" style="margin-top:12px">
    <h2>Neuen Button anlegen (optional)</h2>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=e($csrf)?>">
      <input type="hidden" name="action" value="quick_add">
      <div class="row">
        <label>Titel<br><input class="input" type="text" name="title" placeholder="z. B. Module verwalten"></label>
        <label>Link (href)<br><input class="input" type="text" name="href" placeholder="/backend/tools/modules_manager.php"></label>
      </div>
      <div class="row">
        <label>Icon (optional)<br><input class="input" type="text" name="icon" placeholder="settings"></label>
        <label>Erforderliche Capability (optional)<br><input class="input" type="text" name="cap" placeholder="z. B. view_admin"></label>
      </div>
      <div class="row">
        <label>Zone<br>
          <select class="input" name="section">
            <option value="main">main</option>
            <option value="header">header</option>
            <option value="sidebar">sidebar</option>
            <option value="footer">footer</option>
          </select>
        </label>
        <div style="display:flex;align-items:end"><button class="ppc-button">Anlegen</button></div>
      </div>
    </form>
  </div>
</div>
</body>
</html>
