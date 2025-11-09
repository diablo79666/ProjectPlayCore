<?php
// @admin-button: DB Health Check | /backend/tools/db_structure_check.php | System | 50
// ============================================================================
// ProjectPlayCore – Datenbank-Strukturprüfung
// Pfad: /backend/tools/db_structure_check.php
// Zweck: Prüft, ob zentrale Tabellen wie modules, admin_buttons, users etc.
//        korrekt angelegt und vollständig sind.
// Aufruf: manuell über das Admin-Dashboard
// ============================================================================

declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../../config.php';

ppc_security_headers();
ppc_require_login();

$db = ppc_db();
$user = ppc_current_user() ?? 'unbekannt';

// --------------------------------------------------------------
// 1. Sollstruktur definieren
// --------------------------------------------------------------
$expected = [
    'modules' => [
        'name'        => 'VARCHAR(64)',
        'version'     => 'VARCHAR(32)',
        'enabled'     => 'TINYINT(1)',
        'installed_at'=> 'TIMESTAMP',
    ],
    'admin_buttons' => [
        'title'      => 'VARCHAR(255)',
        'href'       => 'VARCHAR(255)',
        'btn_group'  => 'VARCHAR(100)',
        'sort_order' => 'INT(11)',
        'enabled'    => 'TINYINT(1)',
    ],
    'users' => [
        'id'       => 'INT',
        'username' => 'VARCHAR(100)',
        'email'    => 'VARCHAR(255)',
        'role'     => 'VARCHAR(50)',
    ],
    'roles' => [
        'id'   => 'INT',
        'role' => 'VARCHAR(50)',
        'desc' => 'VARCHAR(255)',
    ],
];

// --------------------------------------------------------------
// 2. Tabellen prüfen
// --------------------------------------------------------------
$results = [];

foreach ($expected as $table => $columns) {
    try {
        $st = $db->query("SHOW COLUMNS FROM `$table`");
        $actualCols = $st ? array_column($st->fetchAll(PDO::FETCH_ASSOC), 'Field') : [];

        $missing = array_diff(array_keys($columns), $actualCols);
        $extra   = array_diff($actualCols, array_keys($columns));

        if (!$actualCols) {
            $results[$table] = ['status' => 'missing', 'missing' => array_keys($columns), 'extra' => []];
        } elseif ($missing) {
            $results[$table] = ['status' => 'partial', 'missing' => $missing, 'extra' => $extra];
        } else {
            $results[$table] = ['status' => 'ok', 'missing' => [], 'extra' => $extra];
        }
    } catch (Throwable $t) {
        $results[$table] = ['status' => 'error', 'missing' => array_keys($columns), 'extra' => [], 'msg' => $t->getMessage()];
    }
}

// --------------------------------------------------------------
// 3. HTML-Ausgabe
// --------------------------------------------------------------
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Datenbankstruktur prüfen</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
body.ppc-container { background:#0b0d10;color:#e7ecef;font-family:sans-serif; }
.wrap{max-width:1100px;margin:2rem auto;padding:1rem;}
.card{background:#11151a;border:1px solid #1e252c;border-radius:12px;padding:16px;margin-bottom:16px;}
h1{margin:0 0 .6rem 0;}
.ok{color:#9fe3bd;} .warn{color:#f3e29b;} .err{color:#f3a1a1;}
table{width:100%;border-collapse:collapse;margin-top:.5rem;}
th,td{padding:.4rem .6rem;border-bottom:1px solid #222;text-align:left;}
th{color:#cbd5e1;text-transform:uppercase;font-size:.8rem;letter-spacing:.05rem;}
</style>
</head>
<body class="ppc-container">
<div class="wrap">
<h1>Datenbank-Strukturprüfung</h1>
<p class="muted">Angemeldet als: <strong><?= e($user) ?></strong></p>

<div style="margin:.8rem 0;">
  <a class="ppc-button-secondary" href="/backend/">Zurück zum Dashboard</a>
</div>

<?php foreach ($results as $table => $res): ?>
  <div class="card">
    <h2><?= e($table) ?></h2>
    <?php if ($res['status'] === 'ok'): ?>
      <p class="ok">✅ Struktur vollständig</p>
    <?php elseif ($res['status'] === 'missing'): ?>
      <p class="err">❌ Tabelle fehlt vollständig!</p>
    <?php elseif ($res['status'] === 'partial'): ?>
      <p class="warn">⚠️ Tabelle vorhanden, aber unvollständig.</p>
      <?php if (!empty($res['missing'])): ?>
        <p>Fehlende Spalten: <strong><?= e(implode(', ', $res['missing'])) ?></strong></p>
      <?php endif; ?>
      <?php if (!empty($res['extra'])): ?>
        <p>Nicht erwartete Spalten: <strong><?= e(implode(', ', $res['extra'])) ?></strong></p>
      <?php endif; ?>
    <?php else: ?>
      <p class="err">❌ Fehler: <?= e($res['msg'] ?? 'Unbekannt') ?></p>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
</body>
</html>
