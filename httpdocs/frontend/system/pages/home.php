<?php
// Öffentliche Startseite (Override)
ppc_security_headers();
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>ProjectPlayCore – Start</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
.hero{max-width:980px;margin:2rem auto;padding:1rem}
.hero .card{border:1px solid #222;border-radius:12px;padding:18px;background:#0c0c0c}
.row{display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.8rem}
</style>
</head>
<body class="ppc-container">
  <div class="hero">
    <div class="card">
      <h1>Willkommen bei ProjectPlayCore</h1>
      <p>Diese Seite ist per Override editierbar (Datei: <code>/frontend/overrides/system/pages/home.php</code>). Inhalte später über ProjectPlayPress-Editor.</p>
      <div class="row">
        <a class="ppc-button" href="/login">Anmelden</a>
        <a class="ppc-button-secondary" href="/profil">Zum Profil</a>
        <a class="ppc-button-secondary" href="/backend/">Admin-Dashboard</a>
      </div>
    </div>
  </div>
</body>
</html>
