<?php
// @admin-button: DB Struktur reparieren | /backend/tools/db_structure_repair.php | System | 51
// ============================================================================
// ProjectPlayCore – Datenbank-Struktur-Reparatur
// Pfad: /backend/tools/db_structure_repair.php
// Zweck: Fügt fehlende Spalten in Kern-Tabellen automatisch hinzu.
// Log: /storage/logs/db_repair.log
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

$logDir = PPC_STORAGE . '/logs';
@mkdir($logDir, 0775, true);
$logFile = $logDir . '/db_repair.log';

function log_write(string $msg): void {
    global $logFile;
    @file_put_contents($logFile, '[' . date('c') . '] ' . $msg . "\n", FILE_APPEND);
}

// --------------------------------------------------------------
// 1. Soll-Struktur definieren
// --------------------------------------------------------------
$expected = [
    'modules' => [
        'name'        => 'VARCHAR(64) NOT NULL',
        'version'     => 'VARCHAR(32) DEFAULT NULL',
        'enabled'     => 'TINYINT(1) DEFAULT 1',
        'installed_at'=> 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP'
    ],
    'admin_buttons' => [
        'title'      => 'VARCHAR(255) NOT NULL',
        'href'       => 'VARCHAR(255) NOT NULL',
        'btn_group'  => 'VARCHAR(100) DEFAULT "System"',
        'sort_order' => 'INT(11) DEFAULT 0',
        'enabled'    => 'TINYINT(1) DEFAULT 1',
    ],
    'users' => [
        'id'       => 'INT AUTO_INCREMENT PRIMARY KEY',
        'username' => 'VARCHAR(100) NOT NULL',
        'email'    => 'VARCHAR(255)',
        'role'     => 'VARCHAR(50) DEFAULT "user"',
    ],
    'roles' => [
        'id'   => 'INT AUTO_INCREMENT PRIMARY KEY',
        'role' => 'VARCHAR(50) NOT NULL',
        'desc' => 'VARCHAR(255) DEFAULT NULL',
    ],
];

$repairs = [];

// --------------------------------------------------------------
// 2. Tabellen prüfen & fehlende Spalten anlegen
// --------------------------------------------------------------
foreach ($expected as $table => $columns) {
    try {
        $st = $db->query("SHOW COLUMNS FROM `$table`");
        $actualCols = $st ? array_column($st->fetchAll(PDO::FETCH_ASSOC), 'Field') : [];

        $missing = array_diff(array_keys($columns), $actualCols);
        if (!$missing) {
            $repairs[$table] = ['status' => 'ok', 'actions' => []];
            continue;
        }

        $actions = [];
        foreach ($missing as $col) {
            $type = $columns[$col];
            $sql = "ALTER TABLE `$table` ADD COLUMN `$col` $type";
            try {
                $db->exec($sql);
                $actions[] = "✅ Spalte '$col' hinzugefügt";
                log_write("[{$table}] Added column '$col' ($type)");
            } catch (Throwable $t) {
                $actions[] = "❌ Fehler bei '$col': " . $t->getMessage();
                log_write("[{$table}] ERROR adding '$col': " . $t->getMessage());
            }
        }
        $repairs[$table] = ['status' => 'fixed', 'actions' => $actions];
    } catch (Throwable $t) {
        $repairs[$table] = ['status' => 'error', 'actions' => ["❌ Fehler: " . $t->getMessage()]];
        log_write("[{$table}] ERROR general: " . $t->getMessage());
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
<title>Datenbank-Struktur reparieren</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
body.ppc-container { background:#0b0d10; color:#e7ecef; font-family:sans-serif; }
.wrap { max-width:1100px; margin:2rem auto; padding:1rem; }
.card { background:#11151a; border:1px solid #1e252c; border-radius:12px; padding:16px; margin-bottom:16px; }
h1 { margin:0 0 .6rem 0; }
.ok { color:#9fe3bd; } .warn { color:#f3e29b; } .err { color:#f3a1a1; }
ul { margin:.5rem 0 .5rem 1rem; }
</style>
</head>
<body class="ppc-container">
<div class="wrap">
<h1>Datenbank-Struktur-Reparatur</h1>
<p class="muted">Angemeldet als: <strong><?= e($user) ?></strong></p>

<div style="margin:.8rem 0;">
  <a class="ppc-button-secondary" href="/backend/">Zurück zum Dashboard</a>
  <a class="ppc-button" href="/backend/tools/db_structure_check.php">Struktur erneut prüfen</a>
</div>

<?php foreach ($repairs as $table => $r): ?>
  <div class="card">
    <h2><?= e($table) ?></h2>
    <?php if ($r['status'] === 'ok'): ?>
      <p class="ok">✅ Tabelle bereits vollständig.</p>
    <?php elseif ($r['status'] === 'fixed'): ?>
      <p class="warn">🛠️ Tabelle repariert:</p>
      <ul>
        <?php foreach ($r['actions'] as $a): ?>
          <li><?= e($a) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="err"><?= implode('<br>', array_map('e', $r['actions'])) ?></p>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<div class="card">
  <h3>Log-Datei</h3>
  <p><code><?= e($logFile) ?></code></p>
</div>
</div>
</body>
</html>
