<?php
// ============================================================================
// ProjectPlayCore – Container Health & Cleanup
// Pfad: /backend/tools/container_health_cleanup.php
// Zweck: Überprüfung und Bereinigung von Container-Einträgen
// Aufrufbar via Cronjob oder Admin-Dashboard
// ============================================================================

declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../../config.php';

ppc_security_headers();
ppc_require_login();

$db = ppc_db();
$log = [];

// --- (1) Containerliste laden ---
$containers = $db->query("SELECT id, name, version, enabled FROM modules")->fetchAll(PDO::FETCH_ASSOC);

// --- (2) HTTP-Check & Deduplikation ---
$seen = [];
foreach ($containers as $c) {
    $id   = (int)$c['id'];
    $name = (string)$c['name'];
    $url  = "https://container.projectplaycore.de/api/{$name}/health";
    $ok   = false;

    // Doppelte Namen direkt deaktivieren
    if (isset($seen[$name])) {
        $db->prepare("UPDATE modules SET enabled=0 WHERE id=:id")->execute([':id'=>$id]);
        $log[] = "🟠 Doppelter Container '$name' deaktiviert (ID $id).";
        continue;
    }
    $seen[$name] = true;

    // Health-Check
    try {
        $ctx = stream_context_create(['http' => ['timeout' => 2]]);
        $resp = @file_get_contents($url, false, $ctx);
        $ok = $resp !== false && str_contains($resp, 'ok');
    } catch (Throwable $t) {
        $ok = false;
    }

    // Ergebnis speichern
    $stmt = $db->prepare("UPDATE modules SET enabled=:e WHERE id=:id");
    $stmt->execute([':e' => $ok ? 1 : 0, ':id' => $id]);
    $log[] = $ok
        ? "✅ Container '$name' aktiv."
        : "❌ Container '$name' inaktiv / nicht erreichbar.";
}

// --- (3) Admin-Buttons mit toten Containern deaktivieren ---
$db->exec("
    UPDATE admin_buttons ab
    JOIN modules m ON ab.href LIKE CONCAT('%', m.name, '%')
    SET ab.enabled = 0
    WHERE m.enabled = 0
");
$log[] = "🧹 Buttons inaktiver Container deaktiviert.";

// --- (4) Ausgabe ---
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Container Health & Cleanup</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="ppc-container" style="max-width:900px;margin:2rem auto">
<div class="ppc-card">
  <h1>Container Health & Cleanup</h1>
  <ul>
    <?php foreach ($log as $entry): ?>
      <li><?= htmlspecialchars($entry, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
    <?php endforeach; ?>
  </ul>
  <p><a class="ppc-button" href="/backend/">Zurück zum Dashboard</a></p>
</div>
</body>
</html>
