<?php
// ============================================================================
// ProjectPlayCore – Admin-Button Path Checker
// Pfad: /backend/tools/check_admin_paths.php
// Beschreibung: Überprüft, ob alle admin_buttons auf existierende Tools verweisen
// ============================================================================
declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../../config.php';

ppc_security_headers();
ppc_require_login();

$db = ppc_db();
$user = (string)(ppc_current_user() ?? 'Unbekannt');

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$buttons = $db->query("SELECT id, title, path, btn_group FROM admin_buttons ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);

$toolDir = realpath(__DIR__);
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Admin-Button Pfadprüfung</title>
<style>
body{background:#0b0d10;color:#e7ecef;font-family:system-ui,sans-serif;padding:2rem}
table{width:100%;border-collapse:collapse;margin-top:1rem}
th,td{padding:.6rem;border-bottom:1px solid #1e252c;text-align:left}
th{color:#9fe3bd}
tr.missing td{background:#2a1010;color:#f8b4b4}
tr.ok td{background:#102512;color:#9fe3bd}
tr.warn td{background:#252510;color:#f3e29b}
a{color:#9fe3bd;text-decoration:none}
a:hover{text-decoration:underline}
.btn{padding:.3rem .8rem;border-radius:6px;background:#1b2026;color:#fff;border:1px solid #2f3a42}
.btn:hover{background:#2a3038}
</style>
</head>
<body>
<h1>Admin-Button Pfadprüfung</h1>
<p>Angemeldet als <strong><?=e($user)?></strong></p>
<table>
<thead><tr><th>ID</th><th>Titel</th><th>Pfad</th><th>Status</th><th>Empfohlener Pfad</th></tr></thead>
<tbody>
<?php
foreach ($buttons as $b) {
    $path = trim($b['path']);
    $full = $_SERVER['DOCUMENT_ROOT'] . $path;
    $exists = $path && file_exists($full);
    $suggest = '';

    // Versuch: Namen anhand des Titels erraten
    $suggestName = strtolower(str_replace([' ', '–', '—'], '_', $b['title']));
    $guess = "/backend/tools/" . $suggestName . ".php";
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . $guess)) {
        $suggest = $guess;
    }

    if ($exists) {
        echo "<tr class='ok'><td>{$b['id']}</td><td>".e($b['title'])."</td><td>".e($b['path'])."</td><td>✅ Gefunden</td><td></td></tr>";
    } else {
        echo "<tr class='missing'><td>{$b['id']}</td><td>".e($b['title'])."</td><td>".e($b['path'])."</td><td>❌ Nicht gefunden</td><td>".e($suggest ?: '–')."</td></tr>";
    }
}
?>
</tbody>
</table>
</body>
</html>
