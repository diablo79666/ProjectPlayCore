<?php
// ============================================================================
// ProjectPlayCore – Roles Loader
// Pfad: /backend/modules/roles/loader.php
// Beschreibung:
//  Stellt Rollenverwaltung, Berechtigungen und Benutzerrechte (RBAC) bereit.
// ============================================================================

declare(strict_types=1);
require_once __DIR__ . '/../../core/init.php';

require_once __DIR__ . '/../../core/container.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/utils.php';
require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../../../config.php';

// Namespace nutzen
use Core\Container;

// ---------------------------------------------------------------------------
// Rollen-Manager
// ---------------------------------------------------------------------------

if (!function_exists('ppc_role_get_all')) {
    function ppc_role_get_all(PDO $db): array {
        $st = $db->query("SELECT id, role, description FROM roles ORDER BY role ASC");
        return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    }
}

if (!function_exists('ppc_role_add')) {
    function ppc_role_add(PDO $db, string $role, string $desc = ''): bool {
        $st = $db->prepare("INSERT INTO roles(role, description) VALUES(:r, :d)");
        return $st->execute([':r' => $role, ':d' => $desc]);
    }
}

if (!function_exists('ppc_role_delete')) {
    function ppc_role_delete(PDO $db, string $role): bool {
        $st = $db->prepare("DELETE FROM roles WHERE role=:r");
        return $st->execute([':r' => $role]);
    }
}

if (!function_exists('ppc_user_can')) {
    function ppc_user_can(string $cap, ?string $username = null): bool {
        $db = Container::has('db') ? Container::get('db') : ppc_db();
        $user = $username ?? ($_SESSION['ppc_user'] ?? '');
        if (!$user) return false;

        $st = $db->prepare("
            SELECT COUNT(*) 
            FROM user_roles ur 
            JOIN role_permissions rp ON ur.role = rp.role 
            WHERE ur.username = :u AND rp.permission = :p
        ");
        $st->execute([':u' => $user, ':p' => $cap]);
        return (bool)$st->fetchColumn();
    }
}

// ---------------------------------------------------------------------------
// Container-Bindung (einmalig bei Modulinitialisierung)
// ---------------------------------------------------------------------------
if (!Container::has('db')) {
    Container::set('db', ppc_db());
}

Container::set('roles', [
    'get_all'  => 'ppc_role_get_all',
    'add'      => 'ppc_role_add',
    'delete'   => 'ppc_role_delete',
    'user_can' => 'ppc_user_can'
]);
