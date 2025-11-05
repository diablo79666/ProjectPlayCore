<?php
/**
 * Migrationen ausführen – Manuelle Oberfläche (mit „Reparieren“-Button)
 */

declare(strict_types=1);
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../../config.php';

ppc_security_headers();
ppc_require_login();

$runner = __DIR__ . '/../modules/migrations.php';
$runnerLoaded = false;
if (is_file($runner)) { require_once $runner; $runnerLoaded = function_exists('ppc_migrations_run_for_enabled'); }

$mode = (string)($_GET['mode'] ?? 'run');
$result = ['applied'=>[], 'skipped'=>[], 'errors'=>[]];
$msg = ''; $err = '';

try {
    if (!$runnerLoaded) {
        $err = 'Migrations-Runner nicht verfügbar (ppc_migrations_run_for_enabled fehlt).';
    } else {
        if ($mode === 'run') {
            $result = ppc_migrations_run_for_enabled();
            $msg = 'Migrationen ausgeführt.';
        } elseif ($mode === 'status') {
            $msg = 'Status angezeigt.';
        } elseif ($mode === 'repair' && function_exists('ppc_migrations_repair_schema')) {
            $msg = ppc_migrations_repair_schema();
        }
    }
} catch (Throwable $t) {
    $err = $t->getMessage();
}
?>
<!doctype html>
<html lang="de"><head>
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
  <?php elseif ($msg): ?>
    <div class="ppc-alert ok"><strong>OK:</strong> <?= e($msg) ?></div>
  <?php endif; ?>

  <?php if ($runnerLoaded): ?>
  <div class="card">
    <h3>Ergebnis</h3>
    <p class="mono"><strong>applied:</strong> <?= e(implode(', ', $result['applied'] ?: [])) ?: '—' ?></p>
    <p class="mono"><strong>skipped:</strong> <?= e(implode(', ', $result['skipped'] ?: [])) ?: '—' ?></p>
    <p class="mono"><strong>errors:</strong> <?= e(implode(' | ', $result['errors'] ?: [])) ?: '—' ?></p>
    <p><small>Details: <span class="mono"><?= e(PPC_STORAGE) ?>/logs/</span> (migrations.log, db_error.log).</small></p>
  </div>
  <?php else: ?>
  <div class="card"><p>Runner nicht geladen. Prüfe <span class="mono">/backend/modules/migrations.php</span>.</p></div>
  <?php endif; ?>

  <div class="card">
    <h3>Hinweis</h3>
    <p>Auto-Migration läuft zusätzlich automatisch, wenn Module geladen werden (über den Modul-Loader). Diese Seite dient dem manuellen Trigger/Debug.</p>
  </div>
</div>
</body>
</html>
