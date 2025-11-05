<?php
/**
 * Module: roles – Loader (Vollständig)
 * Tabellen + Default-Rollen/Caps + Globale Helfer
 * - Rollen: admin, editor, user, superadmin
 * - Caps:
 *     superadmin  -> hat ALLE Caps (implizit; Shortcut in ppc_user_can)
 *     admin       -> manage_modules, manage_users, edit_content, view_admin, manage_identity_providers
 *     editor      -> edit_content, view_admin
 *     user        -> (leer)
 */

declare(strict_types=1);

// Kern laden
if (!defined('PPC_CONFIG_LOADED')) {
    $root = dirname(__DIR__, 1);        // /httpdocs/backend
    $cfg  = dirname($root) . '/config.php';
    if (!is_file($cfg)) {
        $doc = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
        if ($doc && is_file($doc . '/config.php')) $cfg = $doc . '/config.php';
    }
    if (!is_file($cfg)) {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(500);
        echo "FATAL: config.php nicht gefunden (roles/loader.php).";
        exit;
    }
    require_once $cfg;
}

require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../../core/utils.php';

@mkdir(PPC_STORAGE . '/logs', 0775, true);
function roles_log(string $m): void {
    @file_put_contents(PPC_STORAGE . '/logs/modules.log', '['.date('c')."] [roles] {$m}\n", FILE_APPEND);
}

try {
    $db = ppc_db();

    // Tabellen
    $db->exec("CREATE TABLE IF NOT EXISTS roles (
        role VARCHAR(64) PRIMARY KEY
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS role_caps (
        role VARCHAR(64) NOT NULL,
        cap  VARCHAR(64) NOT NULL,
        UNIQUE KEY uq_role_cap (role, cap),
        KEY idx_role (role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS user_roles (
        username VARCHAR(190) NOT NULL,
        role     VARCHAR(64)  NOT NULL,
        UNIQUE KEY uq_user_role (username, role),
        KEY idx_user (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Default-Rollen + Caps
    $defaults = [
        // superadmin: implizit alle Caps (siehe ppc_user_can Shortcut) + ein paar typische Caps für UI-Anzeigen
        'superadmin' => ['view_admin','manage_users','manage_modules','edit_content','manage_identity_providers'],
        'admin'      => ['view_admin','manage_users','manage_modules','edit_content','manage_identity_providers'],
        'editor'     => ['view_admin','edit_content'],
        'user'       => []
    ];

    $insRole = $db->prepare("INSERT IGNORE INTO roles(role) VALUES(:r)");
    $insCap  = $db->prepare("INSERT IGNORE INTO role_caps(role,cap) VALUES(:r,:c)");
    foreach ($defaults as $r => $caps) {
        $insRole->execute([':r'=>$r]);
        foreach ($caps as $c) $insCap->execute([':r'=>$r, ':c'=>$c]);
    }

} catch (Throwable $t) {
    roles_log('init error: '.$t->getMessage());
}

/* ===================== Globale Helfer ===================== */

if (!function_exists('ppc_user_roles')) {
    function ppc_user_roles(?string $username = null): array {
        try {
            $db = ppc_db();
            $u = $username ?? (ppc_current_user() ?? '');
            if ($u === '') return [];
            $stmt = $db->prepare("SELECT role FROM user_roles WHERE username=:u ORDER BY role ASC");
            $stmt->execute([':u'=>$u]);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            return array_values(array_unique(array_map('strval', $rows ?: [])));
        } catch (Throwable $t) {
            roles_log('ppc_user_roles error: '.$t->getMessage());
            return [];
        }
    }
}

if (!function_exists('ppc_has_role')) {
    function ppc_has_role(string $role, ?string $username = null): bool {
        $roles = ppc_user_roles($username);
        return in_array($role, $roles, true);
    }
}

if (!function_exists('ppc_user_can')) {
    function ppc_user_can(string $cap, ?string $username = null): bool {
        try {
            $u = $username ?? (ppc_current_user() ?? '');
            if ($u === '') return false;

            // SUPERADMIN hat IMMER alle Berechtigungen
            if (ppc_has_role('superadmin', $u)) return true;

            $db = ppc_db();
            $stmt = $db->prepare("
                SELECT 1
                FROM user_roles ur
                JOIN role_caps rc ON rc.role = ur.role
                WHERE ur.username=:u AND rc.cap=:c
                LIMIT 1
            ");
            $stmt->execute([':u'=>$u, ':c'=>$cap]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $t) {
            roles_log('ppc_user_can error: '.$t->getMessage());
            return false;
        }
    }
}

if (!function_exists('ppc_require_role')) {
    function ppc_require_role(string $role): void {
        if (!ppc_has_role($role) && !ppc_has_role('superadmin')) {
            http_response_code(403);
            echo "Zugriff verweigert (Rolle erforderlich: ".e($role).")";
            exit;
        }
    }
}

if (!function_exists('ppc_require_cap')) {
    function ppc_require_cap(string $cap): void {
        if (!ppc_user_can($cap)) {
            http_response_code(403);
            echo "Zugriff verweigert (Capability erforderlich: ".e($cap).")";
            exit;
        }
    }
}
