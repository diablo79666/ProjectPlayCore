<?php
// ============================================================================
// ProjectPlayCore – Session Diagnose Tool
// Pfad: /backend/tools/session_status.php
// Beschreibung: Überprüft das Session-System (Status, Pfade, aktuelle User)
// ============================================================================
declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../../config.php';

ppc_security_headers();
ppc_require_login();

// --- Diagnose-Daten ---------------------------------------------------------
$sessionPath = session_save_path();
$sessionId   = session_id();
$user        = ppc_current_user() ?? '–';

$functions = [
    'ppc_current_user'            => function_exists('ppc_current_user'),
    'ppc_require_login'           => function_exists('ppc_require_login'),
    'ppc_session_regenerate'      => function_exists('ppc_session_regenerate'),
    'ppc_require_login_redirect'  => function_exists('ppc_require_login_redirect'),
];

$allOk = $sessionId && $sessionPath && $functions['ppc_current_user'];
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Session Diagnose – ProjectPlayCore</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/assets/style.css">
<style>
body.ppc-container{background:#0b0d10;color:#e7ecef}
.wrap{max-width:960px;margin:2rem auto;padding:1rem}
.card{border:1px solid #1c2128;background:#0f1318;border-radius:14px;padding:16px;margin-bottom:14px}
.ok{color:#9fe3bd}
.err{color:#f3a1a1}
table{width:100%;border-collapse:collapse;margin-top:.5rem}
th,td{padding:8px;border-top:1px solid #222;text-align:left;font-size:.9rem}
th{color:#9fb0bf;text-transform:uppercase;font-size:.75rem;letter-spacing:.05rem}
</style>
</head>
<body class="ppc-container">
<div class="wrap">
  <div class="card">
    <h1>Session-System Diagnose</h1>
    <p>Hier kannst du prüfen, ob das Session-System ordnungsgemäß läuft.</p>

    <table>
      <tr><th>Status</th><td><?= $allOk ? '<span class="ok">✅ Alles OK</span>' : '<span class="err">❌ Fehler erkannt</span>' ?></td></tr>
      <tr><th>Aktueller Benutzer</th><td><?= htmlspecialchars($user, ENT_QUOTES) ?></td></tr>
      <tr><th>Session-ID</th><td><?= htmlspecialchars($sessionId ?: '–', ENT_QUOTES) ?></td></tr>
      <tr><th>Session-Pfad</th><td><?= htmlspecialchars($sessionPath ?: '–', ENT_QUOTES) ?></td></tr>
    </table>

    <h3>Funktionsprüfung</h3>
    <table>
      <?php foreach ($functions as $fn => $ok): ?>
        <tr><td><code><?= $fn ?></code></td><td><?= $ok ? '<span class="ok">OK</span>' : '<span class="err">Fehlt</span>' ?></td></tr>
      <?php endforeach; ?>
    </table>

    <p style="margin-top:1rem">
      <a class="ppc-button" href="/backend/">Zurück zum Admin-Dashboard</a>
    </p>
  </div>
</div>
</body>
</html>
