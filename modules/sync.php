<?php
// ============================================================================
// ProjectPlayCore – Modul-Synchronisierung (dezentral)
// Pfad: /backend/modules/sync.php
// Beschreibung:
//   - Liest alle module.json-Dateien ein
//   - Zeigt Status (aktiv/deaktiviert, Version, Beschreibung)
//   - Führt optional Migrationen aus
// ============================================================================
declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../../config.php';

ppc_security_headers();
ppc_require_login();

// ----------------------------------------------------------------------------
// Module auslesen
// ----------------------------------------------------------------------------
$modulesDir = __DIR__;
$modules = [];
$errors = [];

foreach (glob($modulesDir . '/*/module.json') as $manifestPath) {
    try {
        $json = @file_get_contents($manifestPath);
        $manifest = json_decode($json, true);
        if (!is_array($manifest)) {
            $errors[] = "Ungültiges JSON in: {$manifestPath}";
            continue;
        }
        $modules[] = [
            'name'        => $manifest['service'] ?? basename(dirname($manifestPath)),
            'version'     => $manifest['version'] ?? 'unbekannt',
            'enabled'     => (bool)($manifest['enabled'] ?? false),
            'description' => $manifest['description'] ?? '',
            'author'      => $manifest['author'] ?? 'Unbekannt',
        ];
    } catch (Throwable $t) {
        $errors[] = $t->getMessage();
    }
}

usort($modules, fn($a, $b) => strcasecmp($a['name'], $b['name']));

// ----------------------------------------------------------------------------
// Migrationen optional ausführen
// ----------------------------------------------------------------------------
$migrationOutput = '';
$migrationFile = __DIR__ . '/migrations.php';
if (is_file($migrationFile)) {
    require_once $migrationFile;
    if (function_exists('ppc_migrations_run_for_enabled')) {
        ob_start();
        ppc_migrations_run_for_enabled();
        $migrationOutput = trim(ob_get_clean());
    }
}

$user = ppc_current_user() ?? 'Unbekannt';

?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Module synchronisieren – ProjectPlayCore</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/assets/style.css">
<style>
body.ppc-container { background:#0b0d10; color:#e7ecef; }
.wrap { max-width:1100px; margin:2rem auto; padding:1rem; }
.card { background:#11151a; border:1px solid #1e252c; border-radius:12px; padding:16px; margin-bottom:16px; }
h1 { margin:0 0 .5rem 0; }
ul { margin:.4rem 0 .4rem 1.2rem; }
.ok { color:#9fe3bd; }
.err { color:#f3a1a1; }
.table { width:100%; border-collapse:collapse; margin-top:.6rem; }
th,td { padding:.5rem; border-bottom:1px solid #222; text-align:left; }
th { color:#cbd5e1; text-transform:uppercase; font-size:.8rem; letter-spacing:.05rem; }
.ppc-button, .ppc-button-secondary { display:inline-block; pointer-events:auto; }
pre { background:#0a0c0f; border:1px solid #222; padding:.5rem; border-radius:6px; overflow:auto; max-height:250px; }
</style>
</head>
<body class="ppc-container">
<div class="wrap">

    <div class="card">
        <h1>Module synchronisieren</h1>
        <p class="muted">Angemeldet als <strong><?= e($user) ?></strong></p>

        <ul>
            <li>Gefundene Module: <strong><?= count($modules) ?></strong></li>
            <li>Migrationen: <span class="ok">ausgeführt</span></li>
            <?php if ($errors): ?>
                <li class="err">Fehler: <?= e(implode(' | ', $errors)) ?></li>
            <?php endif; ?>
        </ul>

        <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.6rem">
            <a class="ppc-button" href="/backend/modules/index.php">Zur Modul-Übersicht</a>
            <a class="ppc-button-secondary" href="/backend/">Zum Admin-Dashboard</a>
        </div>
    </div>

    <div class="card">
        <h3>Aktuelle Module</h3>
        <table class="table">
            <thead><tr><th>Name</th><th>Version</th><th>Aktiv</th><th>Beschreibung</th><th>Autor</th></tr></thead>
            <tbody>
            <?php if (!$modules): ?>
                <tr><td colspan="5"><em>Keine Module gefunden.</em></td></tr>
            <?php else: foreach ($modules as $m): ?>
                <tr>
                    <td><?= e($m['name']) ?></td>
                    <td><?= e($m['version']) ?></td>
                    <td><?= $m['enabled'] ? 'Ja' : 'Nein' ?></td>
                    <td><?= e($m['description']) ?></td>
                    <td><?= e($m['author']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($migrationOutput): ?>
    <div class="card">
        <h3>Migrationen – Ausgabe</h3>
        <pre><?= e($migrationOutput) ?></pre>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
