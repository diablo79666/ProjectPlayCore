<?php
// ============================================================================
// ProjectPlayCore – Container Discovery & Sync
// Erweiterung: automatischer Aufruf des Cleanup-Jobs nach erfolgreichem Sync
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

// ------------------------- (1) Standard-Sync -------------------------------
try {
    if (function_exists('ppc_modules_sync')) {
        ppc_modules_sync();
        $log[] = "✅ Module synchronisiert.";
    }
    if (function_exists('ppc_migrations_run_for_enabled')) {
        ppc_migrations_run_for_enabled();
        $log[] = "✅ Migrationen ausgeführt.";
    }
} catch (Throwable $t) {
    $log[] = "❌ Fehler beim Modul-/Migrationslauf: " . $t->getMessage();
}

// --------------------- (2) Container-Button-Sync --------------------------
try {
    require_once __DIR__ . '/containers_sync.php';
    $log[] = "✅ Container-Buttons aktualisiert.";
} catch (Throwable $t) {
    $log[] = "❌ Fehler beim Container-Button-Sync: " . $t->getMessage();
}

// ------------------ (3) Automatischer Cleanup-Aufruf ----------------------
try {
    $cleanupPath = __DIR__ . '/container_health_cleanup.php';
    if (is_file($cleanupPath)) {
        $output = @file_get_contents("http://localhost/backend/tools/container_health_cleanup.php");
        $log[] = "🧹 Container-Health-Cleanup automatisch ausgeführt.";
        if ($output && stripos($output, 'Fehler') !== false) {
            $log[] = "⚠️ Hinweis: Cleanup meldete mögliche Fehler, bitte prüfen.";
        }
    } else {
        $log[] = "ℹ️ Cleanup-Skript nicht gefunden, übersprungen.";
    }
} catch (Throwable $t) {
    $log[] = "❌ Fehler beim Cleanup-Aufruf: " . $t->getMessage();
}

// ------------------------- (4) Ausgabe ------------------------------------
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Container Sync & Cleanup – ProjectPlayCore</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
body{background:#0e0e0e;color:#eee;font-family:ui-monospace,Consolas,monospace;padding:2rem}
li{margin:.3rem 0}
.ok{color:#9fe3bd}.err{color:#f88}.warn{color:#fbc02d}
</style>
</head>
<body>
<h1>Container Sync & Cleanup</h1>
<ul>
<?php foreach ($log as $l): ?>
  <li><?= htmlspecialchars($l, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
<?php endforeach; ?>
</ul>
<p><a href="/backend/">Zum Admin-Dashboard</a></p>
</body>
</html>
