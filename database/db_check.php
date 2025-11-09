<?php
// ============================================================================
// Pfad: /backend/database/db_check.php
// Zweck: Diagnose – DB-Verbindung & users-Tabelle prüfen
// Zugriff: nur eingeloggt
// ============================================================================
declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/db.php'; // ppc_db()

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
if (!isset($_SESSION['user']) || $_SESSION['user'] === '') {
    header('Location: /user/login.php');
    exit;
}

$css = is_file(dirname(__DIR__, 2) . '/assets/style.css') ? '/assets/style.css'
     : (is_file(dirname(__DIR__, 2) . '/style.css') ? '/style.css' : null);

$ok = true; $msgs = [];

try {
    $pdo = ppc_db();
    $msgs[] = '✅ DB-Verbindung aufgebaut.';
    // users-Struktur prüfen
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!$cols) {
        $ok = false;
        $msgs[] = '❌ Tabelle "users" fehlt.';
    } else {
        $need = ['username','password_hash'];
        foreach ($need as $c) {
            if (!in_array($c, $cols, true)) { $ok = false; $msgs[] = "❌ Spalte '{$c}' fehlt in users."; }
        }
        if ($ok) $msgs[] = '✅ Tabelle "users" hat benötigte Spalten.';
        // mindestens 1 user?
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $msgs[] = "ℹ️ Benutzer in users: {$cnt}";
        // test: lädst du deinen Datensatz?
        $check = $pdo->query("SELECT username, LENGTH(password_hash) AS ph_len, LENGTH(password) AS p_len FROM users LIMIT 5")->fetchAll();
        if ($check) { $msgs[] = '🔎 Beispiel-Einträge: ' . htmlspecialchars(json_encode($check), ENT_QUOTES); }
    }
} catch (Throwable $e) {
    $ok = false;
    $msgs[] = '❌ DB-Fehler: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>DB-Check – ProjectPlayCore</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ($css): ?><link rel="stylesheet" href="<?php echo $css; ?>"><?php endif; ?>
<style>
:root{--bg:#0f1722;--fg:#e5e7eb;--ok:#10b981;--err:#ef4444;--card:#111827}
body{font-family:Arial,Helvetica,sans-serif;background:var(--bg);color:var(--fg);margin:0;padding:24px}
.card{background:var(--card);padding:16px;border-radius:12px;max-width:900px;margin:0 auto}
h1{margin:0 0 12px}
.ok{color:var(--ok)} .err{color:var(--err)}
pre{white-space:pre-wrap}
a.button{display:inline-block;padding:8px 12px;border-radius:8px;background:#2563eb;color:#fff;text-decoration:none}
</style>
</head>
<body>
<div class="card">
  <h1>DB-Check</h1>
  <ul>
    <?php foreach ($msgs as $m): ?>
      <li><?php echo $m; ?></li>
    <?php endforeach; ?>
  </ul>
  <p>
    <a class="button" href="/frontend/">Zur Startseite</a>
    <a class="button" href="/user/dashboard.php">Zum Dashboard</a>
  </p>
  <p>Status: <?php echo $ok ? '<strong class="ok">OK</strong>' : '<strong class="err">FEHLER</strong>'; ?></p>
</div>
</body>
</html>
