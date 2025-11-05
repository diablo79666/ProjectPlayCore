<?php
/**
 * ProjectPlayCore – Login (mit optionalem Policy-Hinweis)
 * – Case-insensitive Username-Lookup
 * – password_verify()
 * – CSRF: Double-Submit-Cookie ppc_csrf_login
 * – Nach Erfolg: Session-Regeneration + Redirect Dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/../backend/core/session.php';
require_once __DIR__ . '/../backend/core/security.php';
require_once __DIR__ . '/../backend/core/utils.php';
require_once __DIR__ . '/../backend/database/db.php';
require_once __DIR__ . '/../config.php';

ppc_security_headers();

$db = ppc_db();

// CSRF vorbereiten
$csrfCookie = 'ppc_csrf_login';
if (empty($_COOKIE[$csrfCookie])) {
    $token = bin2hex(random_bytes(16));
    setcookie($csrfCookie, $token, [
        'expires'=> time()+1800, 'path'=>'/', 'secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'),
        'httponly'=> false, 'samesite'=> 'Lax',
    ]);
} else {
    $token = (string)$_COOKIE[$csrfCookie];
}

$err = '';
$username = '';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $posted = (string)($_POST['ppc_csrf_login'] ?? '');
        if (!$posted || !$token || !hash_equals($token, $posted)) {
            throw new RuntimeException('Sicherheitsprüfung fehlgeschlagen (CSRF).');
        }

        $username_raw = (string)($_POST['username'] ?? '');
        $password     = (string)($_POST['password'] ?? '');
        $username     = trim($username_raw);

        if ($username === '' || $password === '') {
            throw new InvalidArgumentException('Bitte Benutzername und Passwort ausfüllen.');
        }

        // Lookup case-insensitiv
        $st = $db->prepare('SELECT username, password_hash FROM users WHERE LOWER(username)=LOWER(:u) LIMIT 1');
        $st->execute([':u'=>$username]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if (!$row || !isset($row['password_hash']) || !password_verify($password, (string)$row['password_hash'])) {
            // Einheitliche Fehlermeldung
            throw new RuntimeException('Anmeldung fehlgeschlagen.');
        }

        $_SESSION['user'] = (string)$row['username'];
        ppc_session_regenerate();

        ppc_redirect('/user/dashboard.php');
        exit;

    } catch (Throwable $t) {
        $err = $t->getMessage();
    }
}

?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Login – ProjectPlayCore</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
  .wrap{max-width:520px;margin:2rem auto;padding:1rem}
  form .row{margin:.6rem 0}
  label{display:block;margin-bottom:.25rem}
  input[type=text],input[type=password]{width:100%;padding:.6rem;border:1px solid #333;background:#0f0f0f;color:#eee;border-radius:8px}
  .flash{margin:.6rem 0;padding:.6rem .8rem;border-radius:8px}
  .flash.err{background:#fdecea;border:1px solid #f1998e}
  .hint{font-size:.9rem;color:#9aa}
  .policy{display:none;margin-top:.5rem;border:1px solid #254;padding:.6rem;border-radius:8px;background:#0b1510;color:#cfe}
  .actions{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap}
  .aslink{background:none;border:none;color:#6cf;cursor:pointer;padding:0;font:inherit;text-decoration:underline}
</style>
<script>
  function togglePolicy(){
    var el=document.getElementById('pw-policy');
    if(!el) return;
    el.style.display = (el.style.display==='block' ? 'none' : 'block');
  }
</script>
</head>
<body class="ppc-container">
<div class="wrap">
  <h1>Anmelden</h1>

  <?php if ($err): ?><div class="flash err"><?=e($err)?></div><?php endif; ?>

  <form method="post" action="/user/login.php" novalidate>
    <input type="hidden" name="ppc_csrf_login" value="<?=e($token)?>">

    <div class="row">
      <label for="username">Nickname</label>
      <input type="text" id="username" name="username" required autocomplete="username" value="<?=e($username)?>">
      <div class="hint">Nickname ist eindeutig (case-insensitiv) – wie bei der Registrierung gewählt.</div>
    </div>

    <div class="row">
      <label for="password">Passwort</label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
      <div class="actions">
        <button type="button" class="aslink" onclick="togglePolicy()">Password-Regeln anzeigen</button>
      </div>
      <div id="pw-policy" class="policy">
        Mindestens <strong>10 Zeichen</strong> und jeweils mindestens:
        <ul>
          <li>1 Großbuchstabe (A–Z)</li>
          <li>1 Kleinbuchstabe (a–z)</li>
          <li>1 Zahl (0–9)</li>
          <li>1 Sonderzeichen (z. B. ! ? # % …)</li>
        </ul>
      </div>
    </div>

    <div class="row">
      <button type="submit">Login</button>
      <a class="ppc-button-secondary" href="/user/register.php">Account erstellen</a>
    </div>
  </form>
</div>
</body>
</html>
