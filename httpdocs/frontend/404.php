<?php
// ============================================================================
// Pfad: /httpdocs/frontend/404.php
// Beschreibung: Öffentliche 404-Seite (nutzt globales Stylesheet)
// ============================================================================
declare(strict_types=1);
http_response_code(404);

$css = is_file(__DIR__ . '/../assets/style.css') ? '/assets/style.css'
     : (is_file(__DIR__ . '/../style.css') ? '/style.css' : null);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>404 – Seite nicht gefunden</title>
<?php if ($css): ?><link rel="stylesheet" href="<?php echo htmlspecialchars($css, ENT_QUOTES); ?>"><?php endif; ?>
</head>
<body>
  <div class="container">
    <header class="header">
      <h1 class="brand">ProjectPlayCore • 404</h1>
      <nav class="nav">
        <a href="/frontend/">Start</a>
        <a href="/user/login.php">Login</a>
        <a href="/backend/">Admin</a>
      </nav>
    </header>
    <section class="grid">
      <div class="card col-12">
        <div class="h1">Seite nicht gefunden</div>
        <p class="p">Die angeforderte Seite existiert nicht oder wurde verschoben.</p>
        <p><a class="button" href="/frontend/">Zur Startseite</a></p>
      </div>
    </section>
    <footer class="footer">© <?php echo date('Y'); ?> ProjectPlayCore</footer>
  </div>
</body>
</html>

