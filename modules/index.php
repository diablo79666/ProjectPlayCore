<?php
// ============================================================================
// ProjectPlayCore – Module Manager (Debug-Version zur Diagnose)
// Pfad: /backend/modules/index.php
// ============================================================================

declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../../config.php';

ppc_security_headers();
ppc_require_login();

// ============================================================================
// Module laden – dezentral über module.json-Dateien
// ============================================================================
$modulesDir = __DIR__;
$modules = [];

// Alle Module durchgehen
foreach (glob($modulesDir . '/*/module.json') as $manifestPath) {
    $json = @file_get_contents($manifestPath);
    $manifest = json_decode($json, true);

    // =============================
    // Debug-Ausgabe für Diagnose
    // =============================
    if (!$manifest) {
        echo "<pre>❌ JSON-Fehler in: $manifestPath\n→ " . json_last_error_msg() . "</pre>";
        continue;
    } else {
        echo "<pre>✅ JSON geladen aus: $manifestPath\n" . print_r($manifest, true) . "</pre>";
    }

    // Prüfen, ob „service“ existiert
    if (empty($manifest['service'])) {
        echo "<pre>⚠️  Übersprungen, kein 'service' in $manifestPath</pre>";
        continue;
    }

    $modules[] = [
        'name'        => $manifest['service'] ?? basename(dirname($manifestPath)),
        'version'     => $manifest['version'] ?? 'unbekannt',
        'enabled'     => $manifest['enabled'] ?? false,
        'description' => $manifest['description'] ?? '',
        'admin'       => $manifest['admin'] ?? [],
        'path'        => dirname($manifestPath),
    ];
}

// Module sortieren (alphabetisch)
usort($modules, fn($a, $b) => strcmp($a['name'], $b['name']));

$user = ppc_current_user() ?? 'Unbekannt';

// ============================================================================
// HTML-Ausgabe
// ============================================================================
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Module verwalten – ProjectPlayCore (Debug)</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/assets/style.css">
<style>
    body.ppc-container { background:#0b0d10;color:#e7ecef; }
    .wrap { max-width:1100px;margin:2rem auto;padding:1rem; }
    table { width:100%;border-collapse:collapse;margin-top:1rem; }
    th,td { padding:.5rem;border-bottom:1px solid #222;text-align:left;vertical-align:middle; }
    th { color:#cbd5e1;text-transform:uppercase;font-size:.8rem;letter-spacing:.05rem; }
    .actions a { display:inline-block;margin-right:.4rem; }
    .badge { display:inline-block;padding:.15rem .45rem;border:1px solid #333;border-radius:.4rem;font-size:.85rem; }
    .ppc-button, .ppc-button-secondary { display:inline-block;pointer-events:auto; }
</style>
</head>
<body class="ppc-container">
<div class="wrap">
    <h1>Module verwalten (Debug)</h1>
    <p class="muted">Angemeldet als: <strong><?= e($user) ?></strong></p>

    <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin:.6rem 0 1rem 0">
        <a class="ppc-button-secondary" href="/backend/">Zurück zum Admin-Dashboard</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Modul</th>
                <th>Version</th>
                <th>Aktiv</th>
                <th>Beschreibung</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($modules)): ?>
            <tr><td colspan="5"><em>Keine Module gefunden.</em></td></tr>
        <?php else: foreach ($modules as $m): ?>
            <tr>
                <td><?= e($m['name']) ?></td>
                <td><?= e($m['version']) ?></td>
                <td><?= $m['enabled'] ? 'Ja' : 'Nein' ?></td>
                <td><?= e($m['description']) ?></td>
                <td class="actions">
                    <?php if (!empty($m['admin']) && is_array($m['admin'])): ?>
                        <?php foreach ($m['admin'] as $a): ?>
                            <?php
                                $title = $a['title'] ?? 'Öffnen';
                                $href  = $a['href'] ?? '';
                                $cap   = $a['cap'] ?? 'view_admin';
                                $can   = true;
                                if (function_exists('ppc_user_can')) {
                                    $can = ppc_user_can($cap);
                                }
                            ?>
                            <?php if ($can && $href): ?>
                                <a class="ppc-button" href="<?= e($href) ?>"><?= e($title) ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="badge">Keine Admin-Aktion</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
