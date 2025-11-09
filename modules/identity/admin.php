<?php
/**
 * ProjectPlayCore – Modul: identity
 * Admin-Panel für Provider-Einstellungen (DB-basiert)
 *
 * Zugriff erlaubt, wenn:
 *  - ppc_user_can('view_admin') ODER Rolle 'admin' ODER Rolle 'superadmin'
 */

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
<title>Modulverwaltung – <?= e($moduleName) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/assets/style.css">
<style>
body.ppc-container { background:#0b0d10;color:#e7ecef; }
.wrap { max-width:900px;margin:2rem auto;padding:1rem; }
.card { background:#0f1318;border:1px solid #1c2128;border-radius:14px;padding:1.2rem;margin-bottom:1rem; }
h1 { margin-top:0; }
.muted { color:#94a3b8; }
.ppc-actions { margin-top:1rem;display:flex;gap:.8rem;flex-wrap:wrap; }
</style>
</head>
<body class="ppc-container">
<div class="wrap">
  <div class="card">
    <h1>Modulverwaltung: <?= e($moduleName) ?></h1>
    <p class="muted">Version: <?= e($version) ?> · Autor: <?= e($author) ?></p>
    <p><?= e($desc) ?></p>

    <form method="post" action="">
      <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
      <div class="ppc-actions">
        <button type="submit" name="toggle">
          <?= $enabled ? 'Modul deaktivieren' : 'Modul aktivieren' ?>
        </button>
        <a class="ppc-button-secondary" href="/backend/modules/index.php">Zurück zur Modulübersicht</a>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>Status</h2>
    <p>Aktueller Zustand: 
      <strong style="color:<?= $enabled ? '#9fe3bd' : '#f3a1a1' ?>">
        <?= $enabled ? 'Aktiviert' : 'Deaktiviert' ?>
      </strong>
    </p>
  </div>
</div>
</body>
</html>