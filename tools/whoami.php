<?php
/**
 * PPC Tools – whoami
 * Zeigt: aktueller User, DOB, berechnetes Alter, age_band, Rollen, Caps (falls roles-Modul),
 * und ob der Zugriff auf Kinderprofile nach aktueller Logik erlaubt wäre.
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

$dob = null; $age = null; $ageBand = null;
try {
    $st = $db->prepare("SELECT dob, age_band FROM users WHERE username=:u LIMIT 1");
    $st->execute([':u'=>$user]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $dob = $row['dob'] ?? null;
        $ageBand = $row['age_band'] ?? null;
        if ($dob) {
            $ts = strtotime($dob . ' 00:00:00');
            if ($ts !== false) $age = (int)floor((time() - $ts)/31556952);
        }
    }
} catch (Throwable $t) {}

$roles = [];
try {
    $st = $db->prepare("SELECT role FROM user_roles WHERE username=:u ORDER BY role");
    $st->execute([':u'=>$user]);
    $roles = array_map(fn($r)=>$r['role'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
} catch (Throwable $t) {}

function has_cap(string $cap, string $user): bool {
    if (function_exists('ppc_user_can')) {
        try { return (bool)ppc_user_can($cap, $user); } catch (Throwable $t) {}
    }
    if ($cap === 'view_admin') {
        // Fallback: 'admin'-Rolle gilt als view_admin
        global $roles;
        return in_array('admin', $roles, true);
    }
    return false;
}

$canParent = false;
try {
    if ($age !== null && $age >= 21) $canParent = true;
    if (in_array('parent_admin', $roles, true)) $canParent = true;
} catch (Throwable $t) {}

?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>PPC Tools – whoami</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
.wrap{max-width:900px;margin:2rem auto;padding:1rem}
.card{border:1px solid #222;border-radius:12px;padding:14px;background:#0c0c0c;margin-bottom:12px}
.badge{display:inline-block;padding:.15rem .5rem;border:1px solid #555;border-radius:999px;font-size:.85rem;color:#aaa;margin-left:.5rem}
.rowbtns{display:flex;gap:.5rem;flex-wrap:wrap}
</style>
</head>
<body class="ppc-container">
<div class="wrap">
  <div class="card">
    <h2>whoami</h2>
    <p>Angemeldet als: <strong><?=e($user)?></strong></p>
    <p>DOB: <strong><?=e((string)$dob)?></strong> &nbsp;|&nbsp; Alter: <strong><?= e($age===null?'?':(string)$age) ?></strong> &nbsp;|&nbsp; age_band: <strong><?=e((string)$ageBand)?></strong></p>
    <p>Rollen: <?= $roles ? e(implode(', ', $roles)) : '<span class="badge">keine</span>' ?></p>
    <p>view_admin: <strong><?= has_cap('view_admin', $user) ? 'ja' : 'nein' ?></strong></p>
    <p>Kinderprofile erlaubt (Logik ≥21 ODER parent_admin): <strong><?= $canParent ? 'ja' : 'nein' ?></strong></p>
    <div class="rowbtns">
      <a class="ppc-button" href="/backend/modules/profiles/controller.php?action=index">Kinderprofile öffnen</a>
      <a class="ppc-button-secondary" href="/user/dashboard.php">Zum Dashboard</a>
    </div>
  </div>
  <div class="card">
    <h3>Tipps</h3>
    <p>Wenn <em>Kinderprofile</em> nicht erlaubt ist:</p>
    <ul>
      <li>DOB in der DB setzen (z. B. <code>1990-01-01</code>) und Seite neu laden.</li>
      <li>oder Rolle <code>parent_admin</code> zuweisen (siehe Tool unten).</li>
    </ul>
    <div class="rowbtns">
      <a class="ppc-button" href="/backend/tools/grant_self_parent_admin.php">parent_admin vergeben (erfordert view_admin)</a>
    </div>
  </div>
</div>
</body>
</html>
