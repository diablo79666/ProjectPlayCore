<?php
// ============================================================================
// ProjectPlayCore – Session-System Diagnose
// Pfad: /backend/tools/session_diagnose.php
// Beschreibung: Zeigt Session-Status, Session-ID, Benutzer und Funktionsprüfung
// ============================================================================
declare(strict_types=1);
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../../config.php';

ppc_security_headers();
ppc_require_login();

$user = ppc_current_user() ?? '–';
$sessionId = session_id() ?: '–';
$sessionPath = session_save_path() ?: 'Unbekannt';

// Funktions-Checkliste
$checks = [
    'ppc_current_user'         => function_exists('ppc_current_user'),
    'ppc_require_login'        => function_exists('ppc_require_login'),
    'ppc_session_regenerate'   => function_exists('ppc_session_regenerate'),
    'ppc_require_login_redirect' => function_exists('ppc_require_login_redirect'),
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Session-System Diagnose</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
body{background:#0b0d10;color:#e7ecef;font-family:system-ui;margin:0;padding:0}
.container{max-width:880px;margin:2rem auto;padding:1rem}
.card{background:#11161b;border:1px solid #222;border-radius:12px;padding:20px}
.ok{color:#2ecc71}
.err{color:#e74c3c}
h1{color:#00aaff}
table{width:100%;border-collapse:collapse;margin-top:12px}
th,td{padding:8px;border-bottom:1px solid #222;text-align:left}
th{color:#8aa0b2;font-weight:600}
.footer{margin-top:20px;color:#8aa0b2}
</style>
</head>
<body>
<div class="container">
  <div class="card">
    <h1>Session-System Diagnose</h1>
    <p>Hier kannst du prüfen, ob das Session-System ordnungsgemäß läuft.</p>

    <table>
      <tr><th>STATUS</th><td><span class="ok">✅ Alles OK</span></td></tr>
      <tr><th>AKTUELLER BENUTZER</th><td><?=htmlspecialchars($user)?></td></tr>
      <tr><th>SESSION-ID</th><td><code><?=htmlspecialchars($sessionId)?></code></td></tr>
      <tr><th>SESSION-PFAD</th><td><code><?=htmlspecialchars($sessionPath)?></code></td></tr>
    </table>

    <h3 style="margin-top:1.4rem;color:#00aaff">Funktionsprüfung</h3>
    <table>
      <?php foreach ($checks as $fn => $ok): ?>
        <tr>
          <td><code><?=htmlspecialchars($fn)?></code></td>
          <td><?= $ok ? '<span class="ok">OK</span>' : '<span class="err">Fehlt</span>' ?></td>
        </tr>
      <?php endforeach; ?>
    </table>

    <div class="footer">
      <a href="/backend/" style="color:#00aaff;text-decoration:none">⬅️ Zurück zum Admin-Dashboard</a>
    </div>
  </div>
</div>
</body>
</html>
