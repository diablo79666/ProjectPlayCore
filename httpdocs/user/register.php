<?php
/**
 * Registrierung mit KYC-Pflicht (Blocking Flow)
 * – Nickname wählt der User (einzigartig)
 * – E-Mail ist PFLICHT (unique, case-insensitiv)
 * – Passwort-Policy: >=10 Zeichen, min. 1 Groß-, 1 Kleinbuchstabe, 1 Zahl, 1 Sonderzeichen
 * – Speichert NICHT in 'users'; startet KYC-Flow (Identity-Container)
 * – Account wird erst im Webhook (approved) final angelegt (DOB nur vom Provider)
 */
declare(strict_types=1);

require_once __DIR__ . '/../backend/core/session.php';
require_once __DIR__ . '/../backend/core/security.php';
require_once __DIR__ . '/../backend/core/utils.php';
require_once __DIR__ . '/../backend/database/db.php';
require_once __DIR__ . '/../config.php';

ppc_security_headers();

$db = ppc_db();

// CSRF: Double-Submit
$csrfCookie = 'ppc_csrf_reg';
if (empty($_COOKIE[$csrfCookie])) {
    $token = bin2hex(random_bytes(16));
    setcookie($csrfCookie, $token, [
        'expires'=> time()+1800, 'path'=>'/', 'secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'),
        'httponly'=> false, 'samesite'=> 'Lax',
    ]);
} else { $token = (string)$_COOKIE[$csrfCookie]; }

$err=''; $username=''; $email='';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function normalize_nick(string $n): string {
    $n = trim(mb_strtolower($n,'UTF-8'));
    $n = preg_replace('/[^a-z0-9\-]/','-',$n);
    $n = preg_replace('/-+/','-',$n);
    return trim($n,'-');
}
function is_reserved_nick(string $n): bool {
    static $r=['admin','administrator','root','system','support','help','api','mail','smtp','imap','pop','www','owner'];
    return in_array($n,$r,true);
}
function password_policy_ok(string $pw): bool {
    if (strlen($pw) < 10) return false;
    if (!preg_match('/[A-Z]/', $pw)) return false;        // Großbuchstabe
    if (!preg_match('/[a-z]/', $pw)) return false;        // Kleinbuchstabe
    if (!preg_match('/\d/', $pw)) return false;           // Zahl
    if (!preg_match('/[^A-Za-z0-9]/', $pw)) return false; // Sonderzeichen
    return true;
}

if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    try {
        // CSRF
        $posted = (string)($_POST['ppc_csrf_reg']??'');
        if (!$posted || !$token || !hash_equals($token,$posted)) {
            throw new RuntimeException('Sicherheitsprüfung fehlgeschlagen (CSRF).');
        }
        // Eingaben
        $username_raw = (string)($_POST['username']??'');
        $password     = (string)($_POST['password']??'');
        $password2    = (string)($_POST['password2']??'');
        $email_raw    = (string)($_POST['email']??'');
        $tos          = isset($_POST['accept_tos']) && $_POST['accept_tos']==='1';

        $username = normalize_nick($username_raw);
        $email    = trim($email_raw);

        // Validierung Nickname
        if ($username==='') throw new InvalidArgumentException('Bitte einen Nickname angeben.');
        $len = mb_strlen($username,'UTF-8');
        if ($len<3 || $len>24) throw new InvalidArgumentException('Nickname muss 3–24 Zeichen haben.');
        if (is_reserved_nick($username)) throw new InvalidArgumentException('Dieser Nickname ist reserviert.');

        // Validierung E-Mail (PFLICHT)
        if ($email==='') throw new InvalidArgumentException('Bitte eine E-Mail-Adresse angeben.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('E-Mail ist ungültig.');

        // Passwort-Policy
        if (!password_policy_ok($password)) {
            throw new InvalidArgumentException('Passwort erfüllt die Anforderungen nicht (≥10 Zeichen, Groß-/Kleinbuchstaben, Zahl, Sonderzeichen).');
        }
        if ($password!==$password2) throw new InvalidArgumentException('Passwörter stimmen nicht überein.');

        if (!$tos) throw new InvalidArgumentException('Bitte AGB/Datenschutz akzeptieren.');

        // Einmaligkeit prüfen (case-insensitiv) – gegen bestehende Accounts
        $st = $db->prepare('SELECT 1 FROM users WHERE LOWER(username)=LOWER(:u) LIMIT 1');
        $st->execute([':u'=>$username]);
        if ($st->fetchColumn()) throw new RuntimeException('Dieser Nickname ist bereits vergeben.');

        $st = $db->prepare('SELECT 1 FROM users WHERE LOWER(email)=LOWER(:e) LIMIT 1');
        $st->execute([':e'=>$email]);
        if ($st->fetchColumn()) throw new RuntimeException('Diese E-Mail ist bereits vergeben.');

        // Weiter an Identity-Container: KYC-Flow starten (POST-Forward via Session)
        $hashTemp = password_hash($password, PASSWORD_DEFAULT);
        $_SESSION['ppc_pending_reg'] = [
            'username'=>$username,
            'email'=>$email,
            'password_hash_temp'=>$hashTemp,
            // NICHT in users speichern – final erst nach approved Webhook
        ];
        ppc_session_regenerate();

        // Start der KYC-Session im Identity-Container
        ppc_redirect('/backend/modules/identity/controller.php?action=register_start');
        exit;

    } catch (Throwable $t) { $err=$t->getMessage(); }
}

?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Konto erstellen (mit Ausweis) – ProjectPlayCore</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
.wrap{max-width:520px;margin:2rem auto;padding:1rem}
form .row{margin:.6rem 0}
label{display:block;margin-bottom:.25rem}
input[type=text],input[type=password],input[type=email]{width:100%;padding:.6rem;border:1px solid #333;background:#0f0f0f;color:#eee;border-radius:8px}
.flash{margin:.6rem 0;padding:.6rem .8rem;border-radius:8px}
.flash.err{background:#fdecea;border:1px solid #f1998e}
.muted{color:#888}
.req{color:#bbb;font-size:.85rem}
</style>
</head>
<body class="ppc-container">
<div class="wrap">
  <h1>Konto erstellen</h1>
  <p class="muted">Die Anmeldung wird erst abgeschlossen, wenn die Ausweisprüfung erfolgreich war. Wir speichern <em>keine Fotos</em>, nur den Prüfstatus und dein Geburtsdatum.</p>

  <?php if ($err): ?><div class="flash err"><?=e($err)?></div><?php endif; ?>

  <form method="post" action="/user/register.php" novalidate>
    <input type="hidden" name="ppc_csrf_reg" value="<?=e($token)?>">

    <div class="row">
      <label for="username">Nickname (3–24, a-z 0-9 -)</label>
      <input id="username" name="username" required value="<?=e($username)?>" autocomplete="username">
    </div>

    <div class="row">
      <label for="email">E-Mail <span class="req">(Pflicht)</span></label>
      <input id="email" name="email" type="email" required value="<?=e($email)?>" autocomplete="email">
    </div>

    <div class="row">
      <label for="password">Passwort</label>
      <input id="password" name="password" type="password" required autocomplete="new-password" placeholder="Mind. 10 Zeichen, Groß/Klein, Zahl, Sonderzeichen">
    </div>

    <div class="row">
      <label for="password2">Passwort wiederholen</label>
      <input id="password2" name="password2" type="password" required autocomplete="new-password">
    </div>

    <div class="row">
      <label><input type="checkbox" name="accept_tos" value="1" required> Ich akzeptiere AGB & Datenschutz.</label>
    </div>

    <div class="row">
      <button type="submit">Weiter zur Ausweisprüfung</button>
    </div>
  </form>
</div>
</body>
</html>
