<?php
/**
 * ProjectPlayCore – Modul-Navigation (Aggregator)
 * Aggregiert Navigation aus aktivierten Modulen.
 * (Nav bleibt rein; Migrations-Trigger erfolgt NICHT mehr hier.)
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../../config.php';

function ppc_nav_db(): PDO { return ppc_db(); }

function ppc_nav_age(?string $user): ?int {
    if (!$user) return null;
    try {
        $db = ppc_nav_db();
        $st = $db->prepare("SELECT dob FROM users WHERE username=:u LIMIT 1");
        $st->execute([':u'=>$user]);
        $dob = (string)$st->fetchColumn();
        if (!$dob) return null;
        $ts = strtotime($dob.' 00:00:00');
        if ($ts===false) return null;
        return (int)floor((time()-$ts)/31556952);
    } catch (Throwable $t) { return null; }
}

function ppc_nav_has_cap(string $cap, ?string $user): bool {
    if (!$user) return false;
    if (function_exists('ppc_user_can')) {
        try { return (bool)ppc_user_can($cap, $user); } catch (Throwable $t) { /* ignore */ }
    }
    if ($cap === 'view_admin') {
        try {
            $db = ppc_nav_db();
            $st = $db->prepare("SELECT 1 FROM user_roles WHERE username=:u AND role='admin' LIMIT 1");
            $st->execute([':u'=>$user]);
            return (bool)$st->fetchColumn();
        } catch (Throwable $t) { return false; }
    }
    return false;
}

function ppc_nav_caps_ok(array $caps, ?string $user): bool {
    foreach ($caps as $c) { if (!ppc_nav_has_cap((string)$c, $user)) return false; }
    return true;
}

function ppc_nav_enabled_modules(): array {
    $enabled = [];
    try {
        $db = ppc_nav_db();
        $rows = $db->query("SELECT name, enabled FROM modules")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $r) {
            if (!empty($r['name']) && (int)$r['enabled'] === 1) {
                $enabled[strtolower((string)$r['name'])] = true;
            }
        }
    } catch (Throwable $t) { /* still fine */ }
    return $enabled;
}

function ppc_nav_read_manifests(): array {
    $dir = __DIR__;
    $enabledFlags = ppc_nav_enabled_modules();
    $mods = [];
    foreach (glob($dir.'/*', GLOB_ONLYDIR) ?: [] as $modDir) {
        $name = basename($modDir);
        $manifestPath = $modDir . '/manifest.json';
        if (!is_file($manifestPath)) continue;
        $raw = @file_get_contents($manifestPath);
        $data = json_decode($raw ?: 'null', true);
        if (!is_array($data)) continue;
        $manifestName = strtolower((string)($data['name'] ?? $name));
        $isEnabled = $enabledFlags ? !empty($enabledFlags[$manifestName]) : (bool)($data['enabled'] ?? true);
        if (!$isEnabled) continue;
        $mods[] = ['name'=>$manifestName, 'dir'=>$modDir, 'manifest'=>$data];
    }
    return $mods;
}

function ppc_nav_admin_buttons(?string $user): array {
    $buttons = [];
    foreach (ppc_nav_read_manifests() as $mod) {
        $m = $mod['manifest'] ?? [];
        if (!empty($m['admin_nav'])) {
            foreach ($m['admin_nav'] as $btn) {
                if (!ppc_nav_caps_ok($btn['caps'] ?? [], $user)) continue;
                $buttons[] = [
                    'title' => $btn['title'] ?? $mod['name'],
                    'href'  => $btn['href'] ?? '#'
                ];
            }
        }
    }
    return $buttons;
}

function ppc_modules_auth_nav(?string $user): array {
    $items = [];
    $mods = ppc_nav_read_manifests();
    $loggedIn = !empty($user);
    foreach ($mods as $m) {
        $list = $m['manifest']['auth_nav'] ?? [];
        if (!is_array($list)) continue;
        foreach ($list as $it) {
            $visible = strtolower((string)($it['visible_if'] ?? 'always'));
            if ($visible === 'guest' && $loggedIn) continue;
            if ($visible === 'auth'  && !$loggedIn) continue;
            $items[] = [
                'title' => (string)($it['title'] ?? ''),
                'href'  => (string)($it['href']  ?? ''),
                'order' => (int)($it['order'] ?? 1000),
            ];
        }
    }
    usort($items, fn($a,$b)=>($a['order']<=>$b['order']) ?: strcmp($a['title'],$b['title']));
    return $items;
}

function ppc_modules_user_nav(?string $user): array {
    $items = [];
    $mods = ppc_nav_read_manifests();
    $age  = ppc_nav_age($user);
    foreach ($mods as $m) {
        $list = $m['manifest']['user_nav'] ?? [];
        if (!is_array($list)) continue;
        foreach ($list as $it) {
            $caps   = isset($it['caps']) && is_array($it['caps']) ? $it['caps'] : [];
            $minAge = isset($it['min_age']) ? (int)$it['min_age'] : null;
            if ($caps && !ppc_nav_caps_ok($caps, $user)) continue;
            if ($minAge !== null && ($age === null || $age < $minAge)) continue;
            $items[] = [
                'title' => (string)($it['title'] ?? ''),
                'href'  => (string)($it['href']  ?? ''),
                'order' => (int)($it['order'] ?? 1000),
            ];
        }
    }
    usort($items, fn($a,$b)=>($a['order']<=>$b['order']) ?: strcmp($a['title'],$b['title']));
    return $items;
}

function ppc_modules_admin_nav(?string $user): array {
    $items = [];
    $mods = ppc_nav_read_manifests();
    foreach ($mods as $m) {
        $list = $m['manifest']['admin_nav'] ?? [];
        if (!is_array($list)) continue;
        foreach ($list as $it) {
            $caps = isset($it['caps']) && is_array($it['caps']) ? $it['caps'] : [];
            if ($caps && !ppc_nav_caps_ok($caps, $user)) continue;
            $items[] = [
                'title' => (string)($it['title'] ?? ''),
                'href'  => (string)($it['href']  ?? ''),
                'order' => (int)($it['order'] ?? 1000),
            ];
        }
    }
    usort($items, fn($a,$b)=>($a['order']<=>$b['order']) ?: strcmp($a['title'],$b['title']));
    return $items;
}

/**
 * Kein automatischer Migrations-Trigger mehr an dieser Stelle.
 * Optionaler Fallback (aus, sofern nicht explizit aktiviert):
 */
// define('PPC_MIGRATIONS_FALLBACK', false);
if (defined('PPC_MIGRATIONS_FALLBACK') && PPC_MIGRATIONS_FALLBACK) {
    $runner = __DIR__ . '/migrations.php';
    if (is_file($runner)) {
        require_once $runner;
        if (function_exists('ppc_migrations_run_for_enabled')) {
            ppc_migrations_run_for_enabled();
        }
    }
}
