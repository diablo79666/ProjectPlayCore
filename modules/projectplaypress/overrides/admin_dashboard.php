<?php
/**
 * ProjectPlayCore – Admin-Dashboard (erweitert um Container-Buttons)
 * Lädt lokale Buttons (DB) + Container-Buttons (JSON)
 * und zeigt sie automatisch in Gruppen an.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../core/utils.php';
require_once __DIR__ . '/../../../database/db.php';

$db = ppc_db();
$groups = [];

/* --------------------------------- Helper --------------------------------- */
function addBtn(array &$groups, string $group, string $title, string $href, int $order=1000): void {
    $groups[$group][] = [
        'title' => $title,
        'href'  => $href,
        'order' => $order,
    ];
}

/* -------------------------- 1. Lokale Buttons ----------------------------- */
try {
    $st = $db->query("SELECT title, href, group_name, sort_order FROM admin_buttons WHERE enabled=1 ORDER BY group_name, sort_order, title");
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $grp = $r['group_name'] ?: 'System';
        addBtn($groups, $grp, $r['title'], $r['href'], (int)($r['sort_order'] ?? 1000));
    }
} catch (Throwable $t) {
    // Ignorieren, wenn Tabelle noch nicht vorhanden ist
}

/* -------------------------- 2. Container-Buttons -------------------------- */
$containersFile = rtrim(PPC_STORAGE ?? (__DIR__ . '/../../../storage'), '/').'/admin/containers_buttons.json';
if (is_file($containersFile)) {
    try {
        $data = json_decode((string)file_get_contents($containersFile), true);
        if (isset($data['buttons']) && is_array($data['buttons'])) {
            foreach ($data['buttons'] as $btn) {
                $group = (string)($btn['group'] ?? 'Services');
                $title = (string)($btn['title'] ?? '');
                $href  = (string)($btn['href'] ?? '');
                $order = (int)($btn['order'] ?? 1000);
                if ($title && $href) addBtn($groups, $group, $title, $href, $order);
            }
        }
    } catch (Throwable $t) {
        // Falls Datei korrupt
    }
}

/* --------------------------- 3. Sortierung -------------------------------- */
foreach ($groups as &$list) {
    uasort($list, fn($a,$b)=>($a['order'] <=> $b['order']) ?: strcmp($a['title'],$b['title']));
}
unset($list);

/* --------------------------- 4. Rendering --------------------------------- */
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Admin-Dashboard</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
.wrap{max-width:1100px;margin:2rem auto;padding:1rem}
h1{margin-bottom:1rem}
.section{margin:1rem 0}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px}
.card{border:1px solid #222;border-radius:12px;padding:14px;background:#0c0c0c}
a.admin-btn{display:block;border:1px solid #333;border-radius:10px;padding:.75rem;text-decoration:none}
a.admin-btn:hover{border-color:#666}
.small{font-size:.85rem;color:#9aa}
.topbar{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem}
</style>
</head>
<body class="ppc-container">
<div class="wrap">
  <h1>Admin-Dashboard</h1>

  <div class="topbar">
    <a class="ppc-button-small" href="/backend/tools/admin_buttons.php">Admin-Buttons bearbeiten</a>
    <a class="ppc-button-small" href="/backend/tools/containers_sync.php">Container synchronisieren</a>
    <a class="ppc-button-small" href="/backend/tools/migrations_status.php">Migrationen</a>
  </div>

  <?php if (!$groups): ?>
    <div class="card">Keine Admin-Buttons vorhanden.</div>
  <?php else: foreach ($groups as $name=>$items): ?>
    <div class="section">
      <h3><?= e($name) ?></h3>
      <div class="grid">
        <?php foreach ($items as $it): ?>
          <a class="admin-btn" href="<?= e($it['href']) ?>">
            <strong><?= e($it['title']) ?></strong><br>
            <span class="small"><?= e($it['href']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>
</body>
</html>
