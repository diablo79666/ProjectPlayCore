<?php
// ============================================================================
// Pfad: /backend/database/install.php
// Zweck: Erstinstallation / Schema-Update / Modul-Sync (idempotent)
// Aufruf: (eingeloggt) /backend/?tool=install  ODER direkt einbinden
// ============================================================================
declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../modules/loader.php'; // nutzt ppc_modules_sync()

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

$pdo = ppc_db();
$messages = [];

// --- users Tabelle (falls nicht vorhanden) ---
$pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) DEFAULT NULL,
        password VARCHAR(255) DEFAULT NULL,
        email VARCHAR(120) DEFAULT NULL,
        role VARCHAR(30) DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
$messages[] = "✅ Tabelle 'users' geprüft/erstellt.";

// --- modules Tabelle (falls nicht vorhanden) ---
$pdo->exec("
    CREATE TABLE IF NOT EXISTS modules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        version VARCHAR(20) DEFAULT '1.0',
        enabled TINYINT(1) NOT NULL DEFAULT 1,
        installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
$messages[] = "✅ Tabelle 'modules' geprüft/erstellt.";

// Spalten nachrüsten (falls Upgrade)
$cols = $pdo->query("SHOW COLUMNS FROM modules")->fetchAll(PDO::FETCH_COLUMN, 0);
if ($cols && !in_array('enabled', $cols, true)) {
    $pdo->exec("ALTER TABLE modules ADD COLUMN enabled TINYINT(1) NOT NULL DEFAULT 1");
    $messages[] = "🔧 Spalte 'enabled' ergänzt.";
}
if ($cols && !in_array('version', $cols, true)) {
    $pdo->exec("ALTER TABLE modules ADD COLUMN version VARCHAR(20) DEFAULT '1.0'");
    $messages[] = "🔧 Spalte 'version' ergänzt.";
}

// Modul-Ordner scannen & DB syncen
$sync = ppc_modules_sync();
$messages[] = "📦 Module synchronisiert: +{$sync['added']} neu, ~{$sync['updated']} aktualisiert.";

// Optional: Admin-User sicherstellen (nur wenn noch keiner da ist)
$has = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($has === 0) {
    $hash = password_hash('demo123', PASSWORD_DEFAULT);
    $ins = $pdo->prepare("INSERT INTO users (username, password_hash, email, role) VALUES ('admin', ?, NULL, 'admin')");
    $ins->execute([$hash]);
    $messages[] = "👤 Admin-User 'admin' mit Passwort 'demo123' erstellt.";
}

// --- Ausgabe minimal ---
$css = is_file(dirname(__DIR__, 2) . '/assets/style.css') ? '/assets/style.css'
     : (is_file(dirname(__DIR__, 2) . '/style.css') ? '/style.css' : null);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Installation – ProjectPlayCore</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ($css): ?><link rel="stylesheet" href="<?php echo $css; ?>"><?php endif; ?>
</head>
<body>
  <div class="container">
    <div class="card col-12">
      <div class="h1">Installation / Update</div>
      <ul class="p">
        <?php foreach ($messages as $m): ?>
          <li><?php echo htmlspecialchars($m, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
        <?php endforeach; ?>
      </ul>
      <p><a class="button" href="/backend/">Zurück zum Admin</a></p>
    </div>
  </div>
</body>
</html>
