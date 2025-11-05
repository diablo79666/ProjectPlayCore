<?php
// Öffentliche Login-Seite (Override)
ppc_security_headers();
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Login – ProjectPlayCore</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
.wrap{max-width:520px;margin:2.5rem auto;padding:1rem}
.card{border:1px solid #222;border-radius:12px;padding:18px;background:#0c0c0c}
label{display:block;margin:.5rem 0}
input[type=text],input[type=password]{width:100%;padding:.45rem;border:1px solid #333;border-radius:8px;background:#111;color:#ddd}
.row{display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.8rem}
.small{color:#9aa}
</style>
</head>
<body class="ppc-container">
  <div class="wrap">
    <div class="card">
      <h1>Login</h1>
      <form method="post" action="/user/login.php">
        <label>E-Mail
          <input type="text" name="email" autocomplete="username" required>
        </label>
        <label>Passwort
          <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <div class="row">
          <button class="ppc-button" type="submit">Anmelden</button>
          <a class="ppc-button-secondary" href="/">Zur Startseite</a>
        </div>
        <p class="small">Hinweis: Diese Seite ist ein Override. Die eigentliche Login-Logik bleibt unverändert.</p>
      </form>
    </div>
  </div>
</body>
</html>
