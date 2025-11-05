<?php
// ============================================================================
// Pfad: /backend/database/create_user.php
// Zweck: Admin-Formular zum Anlegen/Aktualisieren von Nutzern (DB: users)
// Schutz: nur eingeloggte User; CSRF via Double-Submit-Cookie
// ============================================================================
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/session.php';
require_once dirname(__DIR__) . '/core/utils.php';
require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$me = isset($_SESSION['user']) && is_string($_SESSION['user']) ? $_SESSION['user'] : null;
if ($me === null || $me === '') {
    http_response_code(403);
    echo '<!DOCTYPE html><meta charset="utf-8"><p>403 – Bitte zuerst <a href="/user/login.php">einloggen</a>.</p>';
    exit;
}

// CSRF (Double-Submit-Cookie)
$csrf_cookie_name = 'ppc_csrf_admin';
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$csrf_token = $_COOKIE[$csrf_cookie_name] ?? bin2hex(random_bytes(24));
setcookie($csrf_cookie_name, $csrf_token, [
    'expires'  => time() + 3600,
    'path'     => '/',
    'secure'   => $secure,
    'httponly' => false,
    'samesite' => 'Lax',
]);

$css = is_file(dirname(__DIR__, 2) . '/assets/style.css') ? '/assets/style.css'
     : (is_file(dirname(__DIR__, 2) . '/style.css') ? '/style.css' : null);

$log = [];
$ok  = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // CSRF prüfen
    $sent = isset($_POST[$csrf_cookie_name]) ? (string)$_POST[$csrf_cookie_name] : '';
    $cookie = isset($_COOKIE[$csrf_cookie_name]) ? (string)$_COOKIE[$csrf_cookie_name] : '';
    if (!is_string($sent) || !is_string($cookie) || $sent === '' || $cookie === '' || !hash_equals($cookie, $sent)) {
        $ok = false;
        $log[] = 'Sicherheitsprüfung fehlgeschlagen.';
    } else {
        // Eingaben
        $username = strtolower(trim((string)($_POST['username'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $email    = trim((string)($_POST['email'] ?? ''));
        $role     = trim((string)($_POST['role'] ?? 'user'));
        if ($role === '') $role = 'user';

        if ($username === '' || $password === '') {
            $ok = false;
            $log[] = 'Benutzername und Passwort sind erforderlich.';
        } else {
            try {
                $pdo = ppc_db();

                // Existiert?
                $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
                $stmt->execute([$username]);
                $row = $stmt->fetch();

                $hash = password_hash($password, PASSWORD_DEFAULT);

                if ($row) {
                    // Update Passwort + optional Email + Rolle
                    $upd = $pdo->prepare('UPDATE users SET password_hash = ?, email = NULLIF(?, ""), role = ? WHERE username = ?');
                    $upd->execute([$hash, $email, $role, $username]);
                    $ok = true;
                    $log[] = "Benutzer „{$username}“ aktualisiert.";
                } else {
                    // Insert
                    $ins = $pdo->prepare('INSERT INTO users (username, password_hash, email, role) VALUES (?, ?, NULLIF(?, ""), ?)');
                    $ins->execute([$username, $hash, $email, $role]);
                    $ok = true;
                    $log[] = "Benutzer „{$username}“ angelegt.";
                }
            } catch (Throwable $e) {
                $ok = false;
                $log[] = 'DB-Fehler: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Nutzer anlegen – ProjectPlayCore</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ($css): ?><link rel="stylesheet" href="<?php echo htmlspecialchars($css, ENT_QUOTES); ?>"><?php endif; ?>
<style>
:root{--bg:#0b1420;--fg:#e9eef5;--ok:#17b26a;--err:#ef4444;--card:#0f1722;--bd:#203149}
*{box-sizing:border-box}
body{font-family:Arial,Helvetica,sans-serif;background:var(--bg);color:var(--fg);margin:0;padding:40px}
.container{max-width:740px;margin:0 auto}
.card{background:var(--card);padding:18px;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.35)}
.h1{font-size:22px;margin:0 0 10px}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
label{display:block;margin:8px 0 6px}
input,select{width:100%;padding:10px;border:1px solid var(--bd);border-radius:8px;background:#0d1a2a;color:#e9eef5}
.button{display:inline-block;padding:10px 14px;border-radius:8px;text-decoration:none;background:#00aaff;color:#0b1420;border:0;cursor:pointer}
.button:hover{filter:brightness(1.05)}
.note{font-size:12px;color:#a8b3c2;margin-top:6px}
.msg{margin:10px 0;padding:10px;border-radius:8px}
.ok{background:rgba(23,178,106,.15);border:1px solid rgba(23,178,106,.35)}
.err{background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.35)}
</style>
</head>
<body>
<div class="container">
  <div class="card">
    <div class="h1">Nutzer anlegen / aktualisieren</div>

    <?php if ($ok === true): ?>
      <div class="msg ok">Aktion erfolgreich. <?php echo htmlspecialchars(implode(' ', $log), ENT_QUOTES); ?></div>
    <?php elseif ($ok === false): ?>
      <div class="msg err">Aktion fehlgeschlagen. <?php echo htmlspecialchars(implode(' ', $log), ENT_QUOTES); ?></div>
    <?php endif; ?>

    <form method="post" action="/backend/database/create_user.php" autocomplete="off">
      <input type="hidden" name="ppc_csrf_admin" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>">

      <div class="row">
        <div>
          <label>Benutzername *</label>
          <input name="username" type="text" placeholder="z. B. jan" required>
        </div>
        <div>
          <label>Rolle</label>
          <select name="role">
            <option value="user">user</option>
            <option value="admin">admin</option>
          </select>
        </div>
      </div>

      <label>Passwort *</label>
      <input name="password" type="text" placeholder="z. B. JanTest123!" required>
      <div class="note">Passwort wird als sicherer Hash gespeichert.</div>

      <label>E-Mail (optional)</label>
      <input name="email" type="email" placeholder="name@example.com">

      <div style="margin-top:12px">
        <button class="button" type="submit">Speichern</button>
        <a class="button" href="/user/dashboard.php">Zum Dashboard</a>
      </div>
    </form>
  </div>
</div>
</body>
</html>
