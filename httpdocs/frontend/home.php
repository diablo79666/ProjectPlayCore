<?php
/**
 * Öffentliche Startseite – dynamische Auth-Navigation
 */

declare(strict_types=1);

require_once __DIR__ . '/../backend/core/session.php';
require_once __DIR__ . '/../backend/core/security.php';
require_once __DIR__ . '/../backend/core/utils.php';
require_once __DIR__ . '/../backend/modules/nav.php';
require_once __DIR__ . '/../config.php';

ppc_security_headers();

$user = ppc_current_user();
$authNav = ppc_modules_auth_nav($user);

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>ProjectPlayCore – Start</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
  .wrap{max-width:960px;margin:2rem auto;padding:1rem}
  .rowbtns{display:flex;gap:.5rem;flex-wrap:wrap}
  .hero{border:1px solid #222;border-radius:12px;padding:18px;background:#0c0c0c;margin-bottom:12px}
</style>
</head>
<body class="ppc-container">
<div class="wrap">
  <div class="hero">
    <h1>Willkommen bei ProjectPlayCore</h1>
    <p>Bitte melde dich an oder registriere dich mit Ausweisprüfung.</p>
    <?php if ($authNav): ?>
      <div class="rowbtns">
        <?php foreach ($authNav as $it): ?>
          <a class="ppc-button" href="<?=e($it['href'])?>"><?=e($it['title'])?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
