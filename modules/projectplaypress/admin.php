<?php
// ============================================================================
// ProjectPlayCore – Modulverwaltung: ProjectPlayPress (Admin-Seite)
// Pfad: /backend/modules/projectplaypress/admin.php
// ============================================================================
declare(strict_types=1);

require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/utils.php';
require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../loader.php'; // Modul-System laden

ppc_security_headers();
ppc_require_login();

$db   = ppc_db();
$user = (string)(ppc_current_user() ?? '');

// ---------------------------------------------------------------------------
// 1. Modulname automatisch bestimmen
// ---------------------------------------------------------------------------
$moduleName = basename(__DIR__);

// ---------------------------------------------------------------------------
// 2. Manifest lesen
// ---------------------------------------------------------------------------
$manifestPath = __DIR__ . '/module.json';
$manifest = [];
if (is_file($manifestPath)) {
    $json = @file_get_contents($manifestPath);
    $manifest = json_decode($json ?: 'null', true) ?: [];
}
$version = $manifest['version'] ?? 'unbekannt';
$desc    = $manifest['description'] ?? '(keine Beschreibung)';
$author  = $manifest['author'] ?? 'ProjectPlayCore';

// ---------------------------------------------------------------------------
// 3. Datenbankeintrag lesen (aktiv/inaktiv)
// ---------------------------------------------------------------------------
$enabled = false;
try {
    $st = $db->prepare("SELECT enabled FROM modules WHERE name=:n LIMIT 1");
    $st->execute([':n' => $moduleName]);
    $enabled = (bool)$st->fetchColumn();
} catch (Throwable $t) {}

// ---------------------------------------------------------------------------
// 4. CSRF vorbereiten
// ---------------------------------------------------------------------------
$csrfCookie = $moduleName . '_csrf';
$csrfToken  = bin2hex(random_bytes(16));
setcookie($csrfCookie, $csrfToken, [
    'expires'  => time() + 3600,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => false,
    'samesite' => 'Lax',
]);

// ---------------------------------------------------------------------------
// 5. POST: Aktivierung umschalten
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle'])) {
    $cookie = (string)($_COOKIE[$csrfCookie] ?? '');
    $posted = (string)($_POST['csrf'] ?? '');
    if (!$cookie || !$posted || !hash_equals($cookie, $posted)) {
        http_response_code(403);
        echo "CSRF-Überprüfung fehlgeschlagen.";
        exit;
    }
    $newState = !$enabled;
    try {
        $stmt = $db->prepare("UPDATE modules SET enabled=:e WHERE name=:n");
        $stmt->execute([':e' => (int)$newState, ':n' => $moduleName]);
        $enabled = $newState;
    } catch (Throwable $t) {
        http_response_code(500);
        echo "Fehler beim Umschalten: " . e($t->getMessage());
        exit;
    }
    ppc_redirect("/backend/modules/{$moduleName}/admin.php");
    exit;
}

// ---------------------------------------------------------------------------
// 6. HTML-Ausgabe
// ---------------------------------------------------------------------------
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>ProjectPlayPress – Modulverwaltung</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
body.ppc-container { background:#0b0d10;color:#e7ecef;font-family:sans-serif; }
.wrap { max-width:900px;margin:2rem auto;padding:1rem; }
.card { border:1px solid #222;border-radius:12px;padding:1.2rem;background:#0c0c0c;margin-bottom:1.2rem; }
h1 { margin-top:0;color:#fff; }
h2 { color:#ccc;margin-top:0.5rem; }
button.ppc-button { background:#1b5e20;color:#fff;border:none;padding:0.6rem 1.2rem;border-radius:6px;cursor:pointer; }
button.ppc-button:hover { background:#2e7d32; }
.ppc-button-secondary { display:inline-block;margin-top:1rem;color:#fff;text-decoration:none;border:1px solid #555;padding:0.4rem 1rem;border-radius:6px; }
</style>
</head>
<body class="ppc-container">
<div class="wrap">

  <h1>ProjectPlayPress – Modulverwaltung</h1>
  <p>Angemeldet als: <strong><?= e($user) ?></strong></p>

  <div class="card">
    <h2>Modul-Informationen</h2>
    <p><strong>Version:</strong> <?= e($version) ?><br>
       <strong>Autor:</strong> <?= e($author) ?><br>
       <strong>Beschreibung:</strong> <?= e($desc) ?></p>
  </div>

  <div class="card">
    <h2>Status</h2>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
      <p>Aktueller Zustand: <strong><?= $enabled ? 'Aktiviert' : 'Deaktiviert' ?></strong></p>
      <button class="ppc-button" type="submit" name="toggle">
        <?= $enabled ? 'Deaktivieren' : 'Aktivieren' ?>
      </button>
    </form>
  </div>

  <div class="card">
    <a class="ppc-button-secondary" href="/backend/">Zurück zum Admin-Dashboard</a>
  </div>

</div>
</body>
</html>
