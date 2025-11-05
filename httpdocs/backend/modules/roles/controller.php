<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/utils.php';
require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/loader.php';

ppc_security_headers();
ppc_require_login();

$db   = ppc_db();
$user = ppc_current_user() ?? '';

// Zugriff: erlaubt, wenn EINE Bedingung erfüllt:
// - super_admin ODER
// - Cap 'manage_users' ODER
// - DB-Rolle 'admin' (Fallback)
$mayManage = false;
if (ppc_has_role('super_admin')) {
    $mayManage = true;
} elseif (function_exists('ppc_user_can') && ppc_user_can('manage_users')) {
    $mayManage = true;
} else {
    $st = $db->prepare("SELECT 1 FROM user_roles WHERE username=:u AND role='admin' LIMIT 1");
    $st->execute([':u'=>$user]);
    $mayManage = (bool)$st->fetchColumn();
}
if (!$mayManage) { http_response_code(403); echo "Zugriff verweigert (super_admin oder manage_users oder admin-Rolle erforderlich)."; exit; }

// CSRF
$csrfCookie = 'roles_csrf';
if (!isset($_COOKIE[$csrfCookie])) {
    $token = bin2hex(random_bytes(16));
    setcookie($csrfCookie, $token, [
      'expires'=>time()+3600,'path'=>'/','secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'),
      'httponly'=>false,'samesite'=>'Lax',
    ]);
} else {
    $token = (string)$_COOKIE[$csrfCookie];
}

function csrf_check(string $cookieName, string $fieldName): void {
    $cookie = (string)($_COOKIE[$cookieName] ?? '');
    $posted = (string)($_POST[$fieldName] ?? $_GET[$fieldName] ?? '');
    if (!$cookie || !$posted || !hash_equals($cookie,$posted)) {
        http_response_code(403);
        echo "CSRF-Überprüfung fehlgeschlagen.";
        exit;
    }
}

// Aktionen
$msg=''; $err='';
$action = strtolower((string)($_GET['action'] ?? $_POST['action'] ?? ''));

try {
    if ($action === 'grant' || $action === 'revoke') {
        csrf_check($csrfCookie, 'roles_csrf');
        $u = trim((string)($_GET['u'] ?? $_POST['u'] ?? ''));
        $r = trim((string)($_GET['r'] ?? $_POST['r'] ?? ''));
        if ($u==='' || $r==='') throw new InvalidArgumentException('Parameter u/r fehlen.');

        if ($action==='grant') {
            $db->prepare("INSERT IGNORE INTO user_roles(username,role) VALUES(:u,:r)")->execute([':u'=>$u,':r'=>$r]);
            $msg = "Rolle '{$r}' an '{$u}' vergeben.";
        } else {
            $db->prepare("DELETE FROM user_roles WHERE username=:u AND role=:r")->execute([':u'=>$u,':r'=>$r]);
            $msg = "Rolle '{$r}' bei '{$u}' entzogen.";
        }

    } elseif ($action === 'create_role') {
        csrf_check($csrfCookie, 'roles_csrf');
        $r = trim((string)($_POST['role_new'] ?? ''));
        if (!role_valid($r)) throw new InvalidArgumentException('Ungültiger Rollenname (2–64, a-z0-9_-)');
        $db->prepare("INSERT IGNORE INTO roles(role) VALUES(:r)")->execute([':r'=>$r]);
        $msg = "Rolle '{$r}' angelegt.";

    } elseif ($action === 'delete_role') {
        csrf_check($csrfCookie, 'roles_csrf');
        $r = trim((string)($_POST['role_del'] ?? ''));
        if ($r==='' ) throw new InvalidArgumentException('Rolle fehlt.');
        if (strtolower($r)==='super_admin') throw new RuntimeException('super_admin kann nicht gelöscht werden.');
        // Nur löschen, wenn unbelegt
        $st = $db->prepare("SELECT COUNT(*) FROM user_roles WHERE role=:r");
        $st->execute([':r'=>$r]);
        if ((int)$st->fetchColumn() > 0) throw new RuntimeException('Rolle ist Benutzern zugewiesen – erst Entzug.');
        $db->prepare("DELETE FROM role_caps WHERE role=:r")->execute([':r'=>$r]);
        $db->prepare("DELETE FROM roles WHERE role=:r")->execute([':r'=>$r]);
        $msg = "Rolle '{$r}' gelöscht.";

    } elseif ($action === 'add_cap') {
        csrf_check($csrfCookie, 'roles_csrf');
        $r = trim((string)($_POST['cap_role'] ?? ''));
        $c = trim((string)($_POST['cap_name'] ?? ''));
        if (!role_valid($r) || !cap_valid($c)) throw new InvalidArgumentException('Ungültige Rolle/Capability.');
        $db->prepare("INSERT IGNORE INTO role_caps(role,cap) VALUES(:r,:c)")->execute([':r'=>$r, ':c'=>$c]);
        $msg = "Capability '{$c}' zu Rolle '{$r}' hinzugefügt.";

    } elseif ($action === 'remove_cap') {
        csrf_check($csrfCookie, 'roles_csrf');
        $r = trim((string)($_POST['cap_role'] ?? ''));
        $c = trim((string)($_POST['cap_name'] ?? ''));
        if ($r===''||$c==='') throw new InvalidArgumentException('Rolle/Capability fehlt.');
        $db->prepare("DELETE FROM role_caps WHERE role=:r AND cap=:c")->execute([':r'=>$r, ':c'=>$c]);
        $msg = "Capability '{$c}' aus Rolle '{$r}' entfernt.";
    }

} catch (Throwable $t) { $err = $t->getMessage(); }

// Daten sammeln
$users     = ($r=$db->query("SELECT username FROM users ORDER BY username ASC")) ? ($r->fetchAll(PDO::FETCH_COLUMN,0) ?: []) : [];
$allRoles  = ($r=$db->query("SELECT role FROM roles ORDER BY role ASC")) ? ($r->fetchAll(PDO::FETCH_COLUMN,0) ?: []) : [];
$userRoles = [];
if ($r=$db->query("SELECT username,role FROM user_roles ORDER BY username,role")) {
  while ($row = $r->fetch(PDO::FETCH_ASSOC)) $userRoles[$row['username']][] = $row['role'];
}
$roleCaps = [];
if ($r=$db->query("SELECT role,cap FROM role_caps ORDER BY role,cap")) {
  while ($row = $r->fetch(PDO::FETCH_ASSOC)) $roleCaps[$row['role']][] = $row['cap'];
}

function hasRoleUI(array $map, string $u, string $r): bool { return in_array($r, $map[$u] ?? [], true); }
function capsOf(array $map, string $r): array { return array_values(array_unique($map[$r] ?? [])); }

?>
