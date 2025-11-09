<?php
// ============================================================================
// ProjectPlayCore – Dezentraler Navigations-Aggregator
// Pfad: /backend/modules/nav.php
// Beschreibung:
//   Aggregiert Navigation aus allen aktivierten Module-Containern
//   (auth_nav, user_nav, admin_nav)
//   Keine DB-Abhängigkeit mehr – reine module.json-Auswertung
// ============================================================================
declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../../config.php';

// ============================================================================
// 1. Altersberechnung (für jugendschutzrelevante Navigation)
// ============================================================================
function ppc_nav_age(?string $user): ?int {
    if (!$user) return null;
    try {
        $db = ppc_db();
        $st = $db->prepare("SELECT dob FROM users WHERE username=:u LIMIT 1");
        $st->execute([':u'=>$user]);
        $dob = (string)$st->fetchColumn();
        if (!$dob) return null;
        $ts = strtotime($dob . ' 00:00:00');
        return $ts ? (int)floor((time() - $ts) / 31556952) : null;
    } catch (Throwable $t) {
        return null;
    }
}

// ============================================================================
// 2. Capability-Prüfung (über Rollenmodul oder Fallback)
// ============================================================================
function ppc_nav_has_cap(string $cap, ?string $user): bool {
    if (!$user) return false;
    if (function_exists('ppc_user_can')) {
        try {
            return (bool)ppc_user_can($cap, $user);
        } catch (Throwable $t) {
            return false;
        }
    }
    if ($cap === 'view_admin') {
        try {
            $db = ppc_db();
            $st = $db->prepare("SELECT 1 FROM user_roles WHERE username=:u AND role='admin' LIMIT 1");
            $st->execute([':u'=>$user]);
            return (bool)$st->fetchColumn();
        } catch (Throwable $t) {
            return false;
        }
    }
    return false;
}

function ppc_nav_caps_ok(array $caps, ?string $user): bool {
    foreach ($caps as $c) {
        if (!ppc_nav_has_cap((string)$c, $user)) return false;
    }
    return true;
}

// ============================================================================
// 3. Module lesen (dezentral über module.json)
// ============================================================================
function ppc_nav_read_manifests(): array {
    $dir = __DIR__;
    $mods = [];
    foreach (glob($dir . '/*/module.json') ?: [] as $manifestPath) {
        $raw = @file_get_contents($manifestPath);
        $data = json_decode($raw ?: 'null', true);
        if (!is_array($data)) continue;
        $enabled = (bool)($data['enabled'] ?? false);
        if (!$enabled) continue;

        $mods[] = [
            'name'     => strtolower($data['service'] ?? basename(dirname($manifestPath))),
            'dir'      => dirname($manifestPath),
            'manifest' => $data,
        ];
    }
    return $mods;
}

// ============================================================================
// 4. Navigationstypen (auth_nav, user_nav, admin_nav)
// ============================================================================
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
            if ($visible === 'auth' && !$loggedIn) continue;
            $items[] = [
                'title' => (string)($it['title'] ?? ''),
                'href'  => (string)($it['href'] ?? ''),
                'order' => (int)($it['order'] ?? 1000),
            ];
        }
    }
    usort($items, fn($a, $b) => ($a['order'] <=> $b['order']) ?: strcmp($a['title'], $b['title']));
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
            $caps   = $it['caps'] ?? [];
            $minAge = isset($it['min_age']) ? (int)$it['min_age'] : null;
            if ($caps && !ppc_nav_caps_ok($caps, $user)) continue;
            if ($minAge !== null && ($age === null || $age < $minAge)) continue;
            $items[] = [
                'title' => (string)($it['title'] ?? ''),
                'href'  => (string)($it['href'] ?? ''),
                'order' => (int)($it['order'] ?? 1000),
            ];
        }
    }
    usort($items, fn($a, $b) => ($a['order'] <=> $b['order']) ?: strcmp($a['title'], $b['title']));
    return $items;
}

function ppc_modules_admin_nav(?string $user): array {
    $items = [];
    $mods = ppc_nav_read_manifests();
    foreach ($mods as $m) {
        $list = $m['manifest']['admin_nav'] ?? [];
        if (!is_array($list)) continue;
        foreach ($list as $it) {
            $caps = $it['caps'] ?? [];
            if ($caps && !ppc_nav_caps_ok($caps, $user)) continue;
            $items[] = [
                'title' => (string)($it['title'] ?? ''),
                'href'  => (string)($it['href'] ?? ''),
                'order' => (int)($it['order'] ?? 1000),
            ];
        }
    }
    usort($items, fn($a, $b) => ($a['order'] <=> $b['order']) ?: strcmp($a['title'], $b['title']));
    return $items;
}

// ============================================================================
// 5. Optionaler Fallback: Migrationen bei Bedarf starten
// ============================================================================
if (defined('PPC_MIGRATIONS_FALLBACK') && PPC_MIGRATIONS_FALLBACK) {
    $runner = __DIR__ . '/migrations.php';
    if (is_file($runner)) {
        require_once $runner;
        if (function_exists('ppc_migrations_run_for_enabled')) {
            ppc_migrations_run_for_enabled();
        }
    }
}

