<?php
// ============================================================================
// ProjectPlayCore – Migration Runner Tool
// Pfad: /backend/tools/run_migrations.php
// Beschreibung:
//  Führt Migrationen aller aktivierten Module aus (manuell auslösbar)
// ============================================================================

declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../modules/migrations.php';

ppc_security_headers();
ppc_require_login();

$db = ppc_db();
$mode = $_GET['mode'] ?? 'run';
$res  = null;
$err  = null;

try {
    if ($mode === 'repair') {
        require_once __DIR__ . '/fix_migrations_table.php';
        exit;
    } elseif ($mode === 'status') {
        $stateFile = (defined('PPC_STORAGE') ? PPC_STORAGE : (__DIR__ . '/../../storage')) . '/migrations_state.json';
        $state = is_file($stateFile) ? json_decode(@file_get_contents($stateFile) ?: '{}', true) : [];
        header('Content-Type: text/plain; charset=utf-8');
        echo "=== ProjectPlayCore – Migrationsstatus ===\n";
        foreach ($state as $mod => $files) {
            echo "[$mod]\n";
            foreach ($files as $file => $time)
                echo "  - $file @ $time\n";
        }
        exit;
    } else {
        // hier der Fix:
        $res = ppc_migrations_run_for_enabled($db);
    }
} catch (Throwable $t) {
    $err = $t->getMessage();
}

?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Migrationen ausführen</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
.wrap{max-width:980px;margin:2rem auto;padding:1rem}
.card{border:1px solid #222;border-radius:12px;padding:14px;background:#0c0c0c;margin-bottom:12px}
.actions{display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:.6rem}
.ppc-alert{border:1px solid transparent;border-radius:8px;padding:.6rem .8rem;margin:.6rem 0}
.ppc-alert.ok{background:#ecfdf5;border-color:#10b981;color:#065f46}
.ppc-alert.err{background:#fef2f2;border-color:#ef4444;color:#7f1d1d}
.ppc-alert.info{background:#eff6ff;border-color:#3b82f6;color:#1e3a8a}
.mono{font-family:ui-monospace,SFMono-Regular,Consolas,monospace}
ul{margin:.4rem 0 .2rem 1.2rem}
</style>
</head>
<body class="ppc-container">
<div class="wrap">
  <h1>Migrationen ausführen</h1>

  <div class="actions">
    <a class="ppc-button" href="?mode=run">Normal ausführen</a>
    <a class="ppc-button-secondary" href="?mode=status">Status ansehen</a>
    <a class="ppc-button-secondary" href="?mode=repair">Reparieren (Schema prüfen)</a>
    <a class="ppc-button-secondary" href="/backend/">Admin-Dashboard</a>
  </div>

  <?php if ($err): ?>
    <div class="ppc-alert err"><strong>Fehler:</strong> <?= e($err) ?></div>
  <?php elseif ($res): ?>
    <div class="ppc-alert ok"><strong>Migrationen erfolgreich ausgeführt.</strong></div>
    <div class="card">
      <h3>Ergebnis</h3>
      <p class="mono"><strong>Ausgeführt:</strong> <?= e($res['executed'] ? implode(', ', $res['executed']) : '—') ?></p>
      <p class="mono"><strong>Übersprungen:</strong> <?= e($res['skipped'] ? implode(', ', $res['skipped']) : '—') ?></p>
      <p class="mono"><strong>Fehler:</strong> <?= e($res['errors'] ? implode(' | ', $res['errors']) : '—') ?></p>
    </div>
  <?php else: ?>
    <div class="ppc-alert info">Noch keine Migrationen ausgeführt.</div>
  <?php endif; ?>

  <div class="card">
    <h3>Hinweis</h3>
    <p>Auto-Migration läuft zusätzlich automatisch, wenn Module geladen werden (über den Modul-Loader). Diese Seite dient dem manuellen Trigger/Debug.</p>
  </div>
</div>
</body>
</html>
