<?php
/**
 * PPC Tools – Profile Switch
 * Erlaubt: Eltern (≥21 ODER Rolle parent_admin) dürfen in ein eigenes Kinderprofil „hineinschauen“.
 * Verboten: Ein Kinderprofil darf NIE ins Elternprofil oder irgendein anderes Profil wechseln.
 * Umsetzung:
 *  - Start: action=start&child=<username>
 *    -> prüft Besitz (parents/children), setzt $_SESSION['impersonate_user'] = <child>
 *  - Stop:  action=stop
 *    -> entfernt Impersonation
 *  - Audit wird in child_audit protokolliert
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

function is_parent_allowed(PDO $db, string $u): bool {
    try {
        $st = $db->prepare("SELECT dob FROM users WHERE username=:u LIMIT 1");
        $st->execute([':u'=>$u]);
        if ($dob = $st->fetchColumn()) {
            $ts = strtotime($dob.' 00:00:00');
            if ($ts !== false) {
                $age = (int)floor((time()-$ts)/31556952);
                if ($age >= 21) return true;
            }
        }
    } catch (Throwable $t) {}
    try {
        $st = $db->prepare("SELECT 1 FROM user_roles WHERE username=:u AND role='parent_admin' LIMIT 1");
        $st->execute([':u'=>$u]);
        if ($st->fetchColumn()) return true;
    } catch (Throwable $t) {}
    return false;
}

function ensure_parent(PDO $db, string $username): int {
    $st = $db->prepare("SELECT parent_id FROM parents WHERE username=:u LIMIT 1");
    $st->execute([':u'=>$username]);
    $id = $st->fetchColumn();
    if ($id) return (int)$id;
    $st = $db->prepare("INSERT INTO parents (username) VALUES (:u)");
    $st->execute([':u'=>$username]);
    return (int)$db->lastInsertId();
}

function child_owned_by_parent(PDO $db, int $parentId, string $childUser): ?int {
    $st = $db->prepare("SELECT child_id FROM children WHERE parent_id=:p AND child_username=:u LIMIT 1");
    $st->execute([':p'=>$parentId, ':u'=>$childUser]);
    $id = $st->fetchColumn();
    return $id ? (int)$id : null;
}

function audit(PDO $db, int $parentId, int $childId, string $action, array $details=[]): void {
    $st = $db->prepare("INSERT INTO child_audit (child_id,parent_id,action,details) VALUES (:c,:p,:a,:d)");
    $st->execute([
        ':c'=>$childId, ':p'=>$parentId, ':a'=>$action, ':d'=>json_encode($details, JSON_UNESCAPED_UNICODE)
    ]);
}

$action = $_GET['action'] ?? 'index';

if ($action === 'start') {
    // Nur Eltern dürfen starten
    if (!is_parent_allowed($db, $user)) {
        http_response_code(403); die('403 – nur Eltern erlaubt.');
    }
    $child = (string)($_GET['child'] ?? '');
    if ($child === '') { http_response_code(400); die('Bad request'); }

    $parentId = ensure_parent($db, $user);
    $childId  = child_owned_by_parent($db, $parentId, $child);
    if (!$childId) { http_response_code(404); die('Profil gehört dir nicht.'); }

    // Setze Impersonation
    $_SESSION['impersonate_user'] = $child;
    audit($db, $parentId, $childId, 'impersonate_start', ['by'=>$user, 'as'=>$child]);

    header('Location: /user/dashboard.php');
    exit;
}

if ($action === 'stop') {
    // Egal wer – Beenden ist ok (Minderjähriger kann so nicht hoch-elevaten)
    $as = $_SESSION['impersonate_user'] ?? null;
    unset($_SESSION['impersonate_user']);
    if ($as) {
        $parentId = ensure_parent($db, $user);
        // childId optional nachschlagen
        $st = $db->prepare("SELECT child_id FROM children WHERE parent_id=:p AND child_username=:u LIMIT 1");
        $st->execute([':p'=>$parentId, ':u'=>$as]);
        if ($cid = $st->fetchColumn()) {
            audit($db, (int)$parentId, (int)$cid, 'impersonate_stop', ['by'=>$user, 'was'=>$as]);
        }
    }
    header('Location: /user/dashboard.php');
    exit;
}

// Index/Banner
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Profil wechseln – ProjectPlayCore</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="ppc-container">
<div style="max-width:800px;margin:2rem auto">
  <h1>Profil wechseln</h1>
  <p>Nutzung über Aktionen <code>?action=start&child=&lt;name&gt;</code> oder <code>?action=stop</code>.</p>
  <p><a class="ppc-button" href="/backend/modules/profiles/controller.php?action=index">Zurück</a></p>
</div>
</body>
</html>
