<?php
// ============================================================================
// Pfad: /httpdocs/frontend/index.php
// Beschreibung: Lädt bevorzugt /frontend/home.php, sonst Fallback-Hinweis.
// Keine weiteren Includes, damit 500er ausgeschlossen sind.
// ============================================================================
declare(strict_types=1);

// 1) Bevorzugt: /frontend/home.php (liegt im selben Ordner)
$homeLocal = __DIR__ . '/home.php';
if (is_file($homeLocal)) {
    require $homeLocal;
    exit;
}

// 2) Notfall-Fallback: Minimaler Hinweis (ohne weitere Abhängigkeiten)
http_response_code(200);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>ProjectPlayCore – Start</title>
</head>
<body>
  <h1>Startseite</h1>
  <p><code>/frontend/home.php</code> wurde nicht gefunden.</p>
</body>
</html>
