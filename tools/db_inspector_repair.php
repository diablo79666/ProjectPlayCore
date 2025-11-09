<?php
/**
 * ProjectPlayCore – Database Inspector & Auto-Repair
 * -------------------------------------------------
 * Prüft Tabellenstrukturen aus allen aktivierten Modulen
 * und erstellt / ergänzt fehlende Tabellen oder Spalten.
 * Ergebnis wird im Admin-Design ausgegeben.
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../../config.php';

ppc_security_headers();
ppc_require_login();

$db   = ppc_db();
$user = (string)(ppc_current_user() ?? '');

// ------------------------------------------------------------
// LOGGING
// ------------------------------------------------------------
function db_autofix_log(string $msg): void {
    $dir = sys_get_temp_dir() . '/ppc_storage/logs';
    @mkdir($dir, 0775, true);
    @file_put_contents($dir . '/db_inspector.log', '[' . date('c') . '] ' . $msg . "\n", FILE_APPEND);
}

// ------------------------------------------------------------
// MODULE EINLESEN
// ------------------------------------------------------------
function db_autofix_enabled_modules(PDO $db): array {
    try {
        $st = $db->query("SELECT name FROM modules WHERE enabled=1 ORDER BY name ASC");
        return $st ? $st->fetchAll(PDO::FETCH_COLUMN, 0) : [];
    } catch (Throwable $t) {
        db_autofix_log("Fehler beim Lesen der Modul-Tabelle: " . $t->getMessage());
        return [];
    }
}

function db_autofix_load_module_json(string $mod): ?array {
    $file = __DIR__ . '/../modules/' . $mod . '/module.json';
    if (!is_file($file)) return null;
    $raw = @file_get_contents($file);
    $data = json_decode($raw ?: 'null', true);
    if (!is_array($data)) return null;
    return $data;
}

// ------------------------------------------------------------
// PRÜFUNG + REPARATUR
// ------------------------------------------------------------
$modules = db_autofix_enabled_modules($db);
$errors  = [];
$repairs = [];

foreach ($modules as $name) {
    $manifest = db_autofix_load_module_json($name);
    if (!$manifest) continue;
    if (empty($manifest['database'])) continue;

    $repairs[$name] = [];
    foreach ($manifest['database'] as $tableName => $fields) {
        try {
            // Tabelle existiert?
            $exists = false;
            $check = $db->query("SHOW TABLES LIKE '" . addslashes($tableName) . "'");
            $exists = (bool)$check->fetchColumn();

            if (!$exists) {
                // Neu anlegen
                $cols = [];
                foreach ($fields as $col => $def) {
                    $cols[] = "`$col` $def";
                }
                $sql = "CREATE TABLE `" . $tableName . "` (" . implode(', ', $cols) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                $db->exec($sql);
                $repairs[$name][] = "Tabelle `$tableName` neu erstellt.";
                db_autofix_log("[{$name}] Tabelle erstellt: {$tableName}");
                continue;
            }

            // Existierende Spalten prüfen
            $columns = [];
            $colsQuery = $db->query("SHOW COLUMNS FROM `" . $tableName . "`");
            if ($colsQuery) {
                foreach ($colsQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $columns[$row['Field']] = $row['Type'];
                }
            }

            // Fehlende Spalten ergänzen
            foreach ($fields as $col => $def) {
                if (!isset($columns[$col])) {
                    $alter = "ALTER TABLE `" . $tableName . "` ADD COLUMN `" . $col . "` " . $def;
                    $db->exec($alter);
                    $repairs[$name][] = "Spalte `$col` in `$tableName` ergänzt.";
                    db_autofix_log("[{$name}] Spalte ergänzt: {$tableName}.{$col}");
                }
            }
        } catch (Throwable $t) {
            $errors[$name][] = $t->getMessage();
            db_autofix_log("[{$name}] Fehler: " . $t->getMessage());
        }
    }
}

// ------------------------------------------------------------
// AUSGABE
// ------------------------------------------------------------
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Database Inspector & Auto-Repair</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/assets/style.css">
<style>
body.ppc-container { background:#0b0d10; color:#e7ecef; font-family:system-ui,Segoe UI,sans-serif; }
.wrap { max-width:1100px; margin:2rem auto; padding:1rem; }
.card { background:#11151a; border:1px solid #1e252c; border-radius:12px; padding:16px; margin-bottom:16px; }
h1 { margin:0 0 .5rem 0; }
ul { margin:.4rem 0 .4rem 1.2rem; }
.ok { color:#9fe3bd; }
.err { color:#f3a1a1; }
.table { width:100%; border-collapse:collapse; margin-top:.6rem; }
th,td { padding:.5rem; border-bottom:1px solid #222; text-align:left; }
th { color:#cbd5e1; text-transform:uppercase; font-size:.8rem; letter-spacing:.05rem; }
pre { background:#0a0c0f; border:1px solid #222; padding:.5rem; border-radius:6px; overflow:auto; max-height:250px; }
.ppc-button, .ppc-button-secondary { display:inline-block; pointer-events:auto; }
</style>
</head>
<body class="ppc-container">
<div class="wrap">

<h1>Database Inspector & Auto-Repair</h1>
<p class="muted">Angemeldet als <strong><?= e($user) ?></strong></p>
<p><a href="/backend/">Zurück zum Admin-Dashboard</a> · <a href="/backend/modules/sync.php">Module synchronisieren</a></p>

<?php foreach ($modules as $name): ?>
  <?php $ok = empty($errors[$name]); ?>
  <div class="card">
    <h3><?= e($name) ?></h3>
    <?php if ($ok): ?>
      <p class="ok">✓ Tabellen sind aktuell.</p>
    <?php else: ?>
      <p class="err">❌ Fehler:<br><?= e(implode(' | ', $errors[$name])) ?></p>
    <?php endif; ?>

    <?php if (!empty($repairs[$name])): ?>
      <pre><?= e(implode("\n", $repairs[$name])) ?></pre>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<div class="card">
  <h3>Log-Datei</h3>
  <pre><?= e(@file_get_contents(sys_get_temp_dir() . '/ppc_storage/logs/db_inspector.log') ?: 'Noch keine Einträge.') ?></pre>
</div>

</div>
</body>
</html>
