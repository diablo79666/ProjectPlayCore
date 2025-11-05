<?php
/**
 * ProjectPlayCore – Frontend Router (Safe Fallback)
 * PHP 8.2 | robust gegen Fehler | loggt nach PPC_STORAGE/logs/web.log
 *
 * Verhalten:
 * 1) Lädt /httpdocs/config.php (falls vorhanden)
 * 2) Versucht /httpdocs/frontend/index.php zu laden (unser Frontend-Router)
 * 3) Fällt andernfalls auf /httpdocs/frontend/home.php zurück
 * 4) Wenn beides fehlt, zeigt eine kleine „It works“-Seite statt 500
 */

declare(strict_types=1);

// --- Logging vorbereiten ----------------------------------------------------
$storage = getenv('PPC_STORAGE') ?: sys_get_temp_dir() . '/ppc_storage';
@mkdir($storage . '/logs', 0775, true);
$logFile = $storage . '/logs/web.log';

set_exception_handler(function(Throwable $e) use ($logFile) {
    @file_put_contents($logFile, '['.date('c').'] EXC '.$e->getMessage()."\n".$e->getTraceAsString()."\n", FILE_APPEND);
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "HTTP 500 – Unhandled exception\n";
    exit;
});
set_error_handler(function($severity, $message, $file, $line) use ($logFile){
    if (!(error_reporting() & $severity)) return false;
    @file_put_contents($logFile, '['.date('c')."] ERR {$message} @ {$file}:{$line}\n", FILE_APPEND);
    // in Fehler wandeln
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// --- Config laden (wenn vorhanden) -----------------------------------------
$cfg = __DIR__ . '/config.php';
if (is_file($cfg)) {
    require_once $cfg;
}

// --- Frontend-Routing -------------------------------------------------------
$frontendDir = __DIR__ . '/frontend';
$frontendIndex = $frontendDir . '/index.php';
$home = $frontendDir . '/home.php';

if (is_file($frontendIndex)) {
    require $frontendIndex;
    exit;
}

if (is_file($home)) {
    require $home;
    exit;
}

// --- Letzter Fallback: einfache OK-Seite (kein 500) ------------------------
http_response_code(200);
?><!doctype html>
<html lang="de"><head>
<meta charset="utf-8">
<title>ProjectPlayCore</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
html,body{background:#0b0d10;color:#e7ecef;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif}
.wrap{max-width:820px;margin:12vh auto;padding:24px;border:1px solid #1b1f24;border-radius:14px;background:#0f1216}
h1{margin:.2rem 0 1rem}
p{color:#a9b4bf}
a.btn{display:inline-block;margin-top:.8rem;padding:.55rem .9rem;border:1px solid #2a3036;border-radius:10px;color:#e7ecef;text-decoration:none}
a.btn:hover{background:#141920}
.mono{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;color:#9db0c0}
</style>
</head><body>
<div class="wrap">
  <h1>It works 🎉</h1>
  <p>Dein Frontend-Router <span class="mono">/frontend/index.php</span> wurde nicht gefunden.
     Diese Fallback-Seite verhindert einen 500-Fehler.</p>
  <p>Lege eine der folgenden Dateien an:</p>
  <ul>
    <li><span class="mono">/httpdocs/frontend/index.php</span> (empfohlen)</li>
    <li><span class="mono">/httpdocs/frontend/home.php</span> (als einfache Startseite)</li>
  </ul>
  <p><a class="btn" href="/backend/">Zum Admin-Dashboard</a></p>
  <p class="mono">Log: <?=htmlspecialchars($logFile, ENT_QUOTES, 'UTF-8')?></p>
</div>
</body></html>
