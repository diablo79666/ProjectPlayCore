<?php
/**
 * PPC Tools – grant_self_parent_admin
 * Vergibt der eigenen Nutzer-ID die Rolle 'parent_admin'.
 * Schutz: erfordert 'view_admin' (entweder via Capability oder admin-Rolle).
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../../config.php';

ppc_security_headers();
ppc_require_login();
$db   = ppc_db();
$user = ppc_current_user();

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function has_cap_view_admin(PDO $db, string $user): bool {
    if (function_exists('ppc_user_can')) {
        try { if (ppc_user_can('view_admin', $user)) return true; } catch (Throwable $t) {}
    }
    try {
        $st = $db->prepare("SELECT 1 FROM user_roles WHERE username=:u AND role='admin' LIMIT 1");
        $st->execute([':u'=>$user]);
        if ($st->fetchColumn()) return true;
    } catch (Throwable $t) {}
    return false;
}

$allowed = has_cap_view_admin($db, $user);
$done = false; $err = '';

if (!$allowed) {
    http_response_code(403);
    $err = "Zugriff verweigert (view_admin erforderlich).";
} else {
    try {
        // Rolle registrieren (idempotent)
        $db->prepare("INSERT IGNORE INTO roles (role) VALUES ('parent_admin')")->execute();
        // Cap-Zuweisungen sind optional – für Eltern-Feature nicht zwingend
        // Rolle dem aktuellen Benutzer geben (idempotent)
        $db->prepare("INSERT IGNORE INTO user_roles (username, role) VALUES (:u, 'parent_admin')")->execute([':u'=>$user]);
        $done = true;
    } catch (Throwable $t) {
        $err = $t->getMessage();
    }
}

?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>PPC Tools – grant parent_admin</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
.wrap{max-width:900px;margin:2rem auto;padding:1rem}
.card{border:1px solid #222;border-radius:12px;padding:14px;background:#0c0c0c;margin-bottom:12px}
.flash{margin:.6rem 0;padding:.6rem .8rem;border-radius:8px}
.flash.ok{background:#e8f5e9;border:1px solid #7cc37c}
.flash.err{background:#fdecea;border:1px solid #f1998e}
.rowbtns{display:flex;gap:.5rem;flex-wrap:wrap}
</style>
</head>
<body class="ppc-container">
<div class="wrap">
  <div class="card">
    <h2>Rolle vergeben: parent_admin</h2>
    <?php if ($done): ?>
      <div class="flash ok">Rolle <code>parent_admin</code> wurde dir zugewiesen.</div>
    <?php else: ?>
      <div class="flash err"><?= e($err ?: 'Unbekannter Fehler') ?></div>
    <?php endif; ?>

    <div class="rowbtns">
      <a class="ppc-button" href="/backend/tools/whoami.php">Status prüfen (whoami)</a>
      <a class="ppc-button-secondary" href="/backend/modules/profiles/controller.php?action=index">Kinderprofile öffnen</a>
      <a class="ppc-button-secondary" href="/user/dashboard.php">Zum Dashboard</a>
    </div>
  </div>
</div>
</body>
</html>
