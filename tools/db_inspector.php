<?php
// @admin-button: Datenbank-Inspektor | /backend/tools/db_inspector.php | System | 18
// ============================================================================
// ProjectPlayCore – Database Inspector (Admin Tool)
// Pfad: /backend/tools/db_inspector.php
// Beschreibung:
//  - Listet alle Tabellen und Spalten der verbundenen Datenbank auf
//  - Zeigt, welche Module welche Tabellen registrieren
//  - Warnung bei fehlenden Tabellen (Modul ≠ DB)
// ============================================================================

declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../modules/loader.php';

ppc_security_headers();
ppc_require_login();

$db = ppc_db();
$user = (string)(ppc_current_user() ?? '');

// Berechtigungsprüfung
if (function_exists('ppc_user_can') && !ppc_user_can('view_admin')) {
    http_response_code(403);
    echo "403 – Zugriff verweigert";
    exit;
}

// ============================================================================
// DB-Informationen abrufen
// ============================================================================
$tables = [];
try {
    $res = $db->query("SHOW TABLES");
    $names = $res ? $res->fetchAll(PDO::FETCH_COLUMN, 0) : [];
    foreach ($names as $t) {
        $info = $db->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
        $tables[$t] = $info;
    }
} catch (Throwable $t) {
    die("Fehler beim Lesen der Tabellen: " . e($t->getMessage()));
}

// ============================================================================
// Zuordnung: Modul → Tabellen (aus module.json)
// ============================================================================
$moduleTables = [];
foreach (glob(__DIR__ . '/../modules/*/module.json') as $manifestPath) {
    $json = @file_get_contents($manifestPath);
    $manifest = json_decode($json ?: 'null', true);
    if (!is_array($manifest)) continue;

    $modName = basename(dirname($manifestPath));
    $dbdef = $manifest['database']['tables'] ?? [];
    foreach ($dbdef as $tableName => $def) {
        $moduleTables[$modName][] = $tableName;
    }
}

// ============================================================================
// HTML-Ausgabe
// ============================================================================
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Datenbank-Inspektor – ProjectPlayCore</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/assets/style.css">
<style>
body.ppc-container { background:#0b0d10; color:#e7ecef; }
.wrap { max-width:1200px; margin:2rem auto; padding:1rem; }
.card { background:#11151a; border:1px solid #1e252c; border-radius:12px; padding:16px; margin-bottom:16px; }
h1 { margin:0 0 .5rem 0; }
h2 { margin-top:1rem; }
table { width:100%; border-collapse:collapse; margin-top:.5rem; }
th,td { padding:.4rem .6rem; border-bottom:1px solid #222; text-align:left; font-size:.9rem; }
th { color:#cbd5e1; text-transform:uppercase; font-size:.8rem; letter-spacing:.05rem; }
.module-box { margin:1rem 0; border:1px solid #333; border-radius:8px; padding:10px 14px; background:#0f1318; }
.ok { color:#9fe3bd; }
.warn { color:#f3e29b; }
.err { color:#f3a1a1; }
</style>
</head>
<body class="ppc-container">
<div class="wrap">
  <h1>Database Inspector</h1>
  <p class="muted">Angemeldet als <strong><?= e($user) ?></strong></p>

  <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin:.6rem 0 1rem 0">
    <a class="ppc-button" href="/backend/">Zurück zum Admin-Dashboard</a>
    <a class="ppc-button-secondary" href="/backend/modules/sync.php">Module synchronisieren</a>
  </div>

  <?php if (empty($tables)): ?>
    <div class="card err">Keine Tabellen gefunden.</div>
  <?php else: ?>
    <?php foreach ($moduleTables as $mod => $tbls): ?>
      <div class="module-box">
        <h2><?= e($mod) ?></h2>
        <?php foreach ($tbls as $tbl): ?>
          <h3><?= e($tbl) ?></h3>
          <?php if (isset($tables[$tbl])): ?>
            <table>
              <thead><tr><th>Feld</th><th>Typ</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr></thead>
              <tbody>
              <?php foreach ($tables[$tbl] as $col): ?>
                <tr>
                  <td><?= e($col['Field']) ?></td>
                  <td><?= e($col['Type']) ?></td>
                  <td><?= e($col['Null']) ?></td>
                  <td><?= e($col['Key']) ?></td>
                  <td><?= e($col['Default'] ?? '') ?></td>
                  <td><?= e($col['Extra']) ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p class="err">❌ Tabelle fehlt in Datenbank!</p>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
</body>
</html>
