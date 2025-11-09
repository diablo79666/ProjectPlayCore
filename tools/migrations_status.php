<?php
// @admin-button: Migration Status Übersicht | /backend/tools/migrations_status.php | System | 18
// ============================================================================
// ProjectPlayCore – Migration Status Übersicht (v3.0)
// Pfad: /backend/tools/migrations_status.php
// Beschreibung:
//  Zeigt alle gespeicherten Migrationen aus /storage/migrations_state.json
//  visuell im Admin-Dashboard-Stil an.
// ============================================================================

declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../../config.php';

ppc_security_headers();
ppc_require_login();

$user = (string)(ppc_current_user() ?? '');

// Datei mit Status einlesen
$stateFile = (defined('PPC_STORAGE') ? PPC_STORAGE : (__DIR__ . '/../../storage')) . '/migrations_state.json';
$state = [];
if (is_file($stateFile)) {
    $json = @file_get_contents($stateFile);
    $state = json_decode($json ?: 'null', true) ?: [];
}

// Letzte Änderung für Anzeige
$lastChange = is_file($stateFile) ? date('d.m.Y H:i:s', filemtime($stateFile)) : '—';
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Migrationsstatus – ProjectPlayCore</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
body.ppc-container { background:#0b0d10; color:#e7ecef; }
.wrap { max-width:1100px; margin:2rem auto; padding:1rem; }
.card { background:#11151a; border:1px solid #1e252c; border-radius:12px; padding:16px; margin-bottom:16px; }
h1 { margin:0 0 .5rem 0; }
h2 { margin-top:1rem; color:#9fe3bd; }
table { width:100%; border-collapse:collapse; margin-top:.5rem; }
th,td { padding:.4rem .6rem; border-bottom:1px solid #222; text-align:left; font-size:.9rem; }
th { color:#cbd5e1; text-transform:uppercase; font-size:.8rem; letter-spacing:.05rem; }
.mono { font-family:ui-monospace,SFMono-Regular,Consolas,monospace; }
.muted { color:#94a3b8; }
.ok { color:#9fe3bd; }
.err { color:#f3a1a1; }
.info { color:#a5b4fc; }
</style>
</head>
<body class="ppc-container">
<div class="wrap">
  <h1>Migrationsstatus</h1>
  <p class="muted">Angemeldet als <strong><?= e($user) ?></strong> – Letzte Änderung: <strong><?= e($lastChange) ?></strong></p>

  <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin:.6rem 0 1rem 0">
    <a class="ppc-button" href="/backend/tools/run_migrations.php">Migrationen ausführen</a>
    <a class="ppc-button-secondary" href="/backend/">Zum Admin-Dashboard</a>
  </div>

  <?php if (empty($state)): ?>
    <div class="card err">Keine gespeicherten Migrationen gefunden. Bitte zuerst „Migrationen ausführen“.</div>
  <?php else: ?>
    <?php foreach ($state as $module => $entries): ?>
      <div class="card">
        <h2><?= e($module) ?></h2>
        <table>
          <thead><tr><th>Datei</th><th>Ausgeführt am</th></tr></thead>
          <tbody>
            <?php foreach ($entries as $file => $date): ?>
              <tr>
                <td class="mono"><?= e($file) ?></td>
                <td><?= e($date) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="card info">
    <h3>Hinweis</h3>
    <p>Die Daten stammen aus <code>storage/migrations_state.json</code> und werden automatisch aktualisiert, wenn Migrationen ausgeführt werden.</p>
  </div>
</div>
</body>
</html>
