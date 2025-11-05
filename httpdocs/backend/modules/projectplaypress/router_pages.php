<?php
/**
 * Public-Renderer-Hook: Rendert Seiten aus ppp_pages VOR dem bestehenden Router.
 * Verwendung in /index.php:
 *   require_once __DIR__.'/backend/modules/projectplaypress/router_pages.php';
 *   if (ppp_render_page_if_exists()) { exit; }
 */

declare(strict_types=1);
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/utils.php';
require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../../../config.php';

function ppp_resolve_slug(): string {
    // 1) Query-Param ?page=… (falls vorhanden)
    $q = isset($_GET['page']) ? trim((string)$_GET['page'], "/") : null;
    if ($q !== null && $q !== '') return strtolower($q);

    // 2) PATH_INFO/REQUEST_URI zu Slug normalisieren
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    $path = trim($path, '/');
    // Root → slug = ''
    return strtolower($path);
}

/** Rendert und gibt true zurück, wenn eine Seite gefunden wurde. */
function ppp_render_page_if_exists(): bool {
    try {
        $db = ppc_db();
        $db->exec("CREATE TABLE IF NOT EXISTS ppp_pages (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(190) NOT NULL UNIQUE,
            title VARCHAR(190) NOT NULL,
            status ENUM('draft','published') NOT NULL DEFAULT 'published',
            content MEDIUMTEXT NULL,
            template VARCHAR(190) NULL,
            override_path VARCHAR(255) NULL,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $slug = ppp_resolve_slug(); // '' für Home
        // Nur veröffentlichte Seiten
        $st = $db->prepare("SELECT * FROM ppp_pages WHERE slug=:s AND status='published' LIMIT 1");
        $st->execute([':s'=>$slug]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) return false;

        $override = (string)($p['override_path'] ?? '');
        if ($override !== '' && is_file($_SERVER['DOCUMENT_ROOT'].$override)) {
            require $_SERVER['DOCUMENT_ROOT'].$override;
            return true;
        }

        // Fallback: einfacher Renderer
        header('Content-Type: text/html; charset=utf-8'); ?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title><?= e((string)$p['title']) ?></title>
<link rel="stylesheet" href="/assets/style.css">
<style>
.wrap{max-width:980px;margin:2rem auto;padding:1rem}
.card{border:1px solid #222;border-radius:12px;padding:14px;background:#0c0c0c}
</style>
</head>
<body class="ppc-container">
  <div class="wrap">
    <div class="card">
      <h1><?= e((string)$p['title']) ?></h1>
      <div><?= (string)$p['content'] ?></div>
    </div>
  </div>
</body>
</html>
<?php
        return true;
    } catch (Throwable $t) {
        // Bei Fehlern nichts rendern → bestehender Router übernimmt
        return false;
    }
}
