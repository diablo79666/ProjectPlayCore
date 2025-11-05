<?php
/**
 * Module verwalten – Übersicht
 * Zeigt „Öffnen“-Aktion dynamisch anhand des Manifest-Admin-Eintrags.
 */
declare(strict_types=1);
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/loader.php';
require_once __DIR__ . '/../../config.php';

ppc_security_headers();
ppc_require_login();

$db = ppc_db();
$mods = [];
try {
  $st = $db->query("SELECT name, version, enabled FROM modules ORDER BY name");
  $mods = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $t) {}
?>
<!doctype html>
<html lang="de"><head>
<meta charset="utf-8"><title>Module verwalten</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
.wrap{max-width:1000px;margin:2rem auto;padding:1rem}
.table{width:100%;border-collapse:collapse}
th,td{padding:.5rem;border-bottom:1px solid #222;text-align:left;vertical-align:middle}
.actions a{display:inline-block;margin-right:.4rem}
.badge{display:inline-block;padding:.15rem .45rem;border:1px solid #333;border-radius:.4rem;font-size:.85rem}
</style>
</head><body class="ppc-container">
<div class="wrap">
  <h1>Module verwalten</h1>
  <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin:.6rem 0 1rem 0">
    <a class="ppc-button" href="/backend/modules/sync.php">Module synchronisieren</a>
    <a class="ppc-button-secondary" href="/backend/">Zurück zum Admin-Dashboard</a>
  </div>
  <table class="table">
    <thead><tr><th>Name</th><th>Version</th><th>Aktiv</th><th>Aktionen</th></tr></thead>
    <tbody>
    <?php if (!$mods): ?>
      <tr><td colspan="4"><em>Keine Einträge gefunden.</em></td></tr>
    <?php else: foreach ($mods as $m):
        $name = (string)$m['name'];
        $manifest = ppc_module_manifest_by_name($name);
        $href = $manifest ? ppc_module_admin_url($name, $manifest) : '';
        $label = $manifest && isset($manifest['admin']['label']) ? (string)$manifest['admin']['label'] : 'Öffnen';
        $cap   = $manifest && isset($manifest['admin']['capability']) ? (string)$manifest['admin']['capability'] : '';
        $canOpen = true;
        if ($cap !== '' && function_exists('ppc_user_has_cap')) {
            $canOpen = ppc_user_has_cap($cap);
        }
    ?>
      <tr>
        <td><?= e($name) ?></td>
        <td><?= e($m['version']) ?></td>
        <td><?= ((int)$m['enabled']===1?'Ja':'Nein') ?></td>
        <td class="actions">
          <?php if ((int)$m['enabled']===1 && $href && $canOpen): ?>
            <a class="ppc-button" href="<?= e($href) ?>"><?= e($label) ?></a>
          <?php else: ?>
            <span class="badge">Keine Aktion</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
</body></html>
