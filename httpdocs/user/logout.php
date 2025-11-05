<?php
// ============================================================================
// Pfad: /user/logout.php
// Zweck: Saubere Abmeldung (Session leeren + Fallback-Cookie "ppc_user" löschen)
// ============================================================================
declare(strict_types=1);

require_once dirname(__DIR__) . '/backend/core/session.php';

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

// 1) Session-Inhalt leeren
$_SESSION = [];

// 2) Session-Cookie invalidieren (falls gesetzt)
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

// 3) Session beenden
@session_destroy();

// 4) Fallback-Login-Cookie entfernen
setcookie('ppc_user', '', [
    'expires'  => time() - 3600,
    'path'     => '/',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);

// 5) Bestätigungsseite
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Abgemeldet – ProjectPlayCore</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/assets/style.css">
<style>
.container{max-width:720px;margin:40px auto}
.center{text-align:center}
.button{display:inline-block;padding:10px 14px;background:#005bbb;color:#fff;border-radius:8px;text-decoration:none}
.button:hover{filter:brightness(0.95)}
</style>
</head>
<body>
  <div class="container">
    <div class="card col-12 center">
      <div class="h1">Erfolgreich abgemeldet</div>
      <p class="p">Du wurdest sicher ausgeloggt. (Session &amp; Cookies wurden entfernt.)</p>
      <p>
        <a class="button" href="/frontend/">Zur Startseite</a>
        <a class="button" href="/user/login.php">Erneut anmelden</a>
      </p>
    </div>
  </div>
</body>
</html>
