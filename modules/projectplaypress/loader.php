<?php
/**
 * Module: projectplaypress – Loader
 * Stellt Helper für Settings & Seiten bereit.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../core/init.php';

if (!defined('PPC_CONFIG_LOADED')) {
    $root = dirname(__DIR__, 2);        // /httpdocs/backend
    $cfg  = dirname($root) . '/config.php';
    if (is_file($cfg)) require_once $cfg;
}

if (!function_exists('ppress_dir')) {
    function ppress_dir(): string {
        $dir = rtrim(PPC_STORAGE, '/').'/modules/projectplaypress';
        @mkdir($dir, 0775, true);
        @mkdir($dir.'/pages', 0775, true);
        return $dir;
    }
}
if (!function_exists('ppress_settings_path')) {
    function ppress_settings_path(): string { return ppress_dir().'/settings.json'; }
}
if (!function_exists('ppress_page_path')) {
    function ppress_page_path(string $slug): string {
        $slug = preg_replace('/[^a-z0-9\-]/i', '-', $slug);
        return ppress_dir()."/pages/{$slug}.html";
    }
}
if (!function_exists('ppress_settings_defaults')) {
    function ppress_settings_defaults(): array {
        return [
            'maintenance' => false,
            'home_slug'   => 'home'
        ];
    }
}
if (!function_exists('ppress_settings_load')) {
    function ppress_settings_load(): array {
        $f = ppress_settings_path();
        if (!is_file($f)) return ppress_settings_defaults();
        $raw = @file_get_contents($f);
        $data = json_decode($raw ?: 'null', true);
        if (!is_array($data)) $data = [];
        return array_replace(ppress_settings_defaults(), $data);
    }
}
if (!function_exists('ppress_settings_save')) {
    function ppress_settings_save(array $s): bool {
        $f = ppress_settings_path();
        $tmp = $f.'.tmp';
        $json = json_encode($s, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if ($json === false) return false;
        if (@file_put_contents($tmp, $json) === false) return false;
        return @rename($tmp, $f);
    }
}
if (!function_exists('ppress_page_get')) {
    function ppress_page_get(string $slug): string {
        $p = ppress_page_path($slug);
        if (!is_file($p)) return '';
        return (string)@file_get_contents($p);
    }
}
if (!function_exists('ppress_page_set')) {
    function ppress_page_set(string $slug, string $html): bool {
        $p = ppress_page_path($slug);
        $tmp = $p.'.tmp';
        if (@file_put_contents($tmp, $html) === false) return false;
        return @rename($tmp, $p);
    }
}
if (!function_exists('ppress_is_maintenance')) {
    function ppress_is_maintenance(): bool {
        $s = ppress_settings_load();
        return !empty($s['maintenance']);
    }
}
if (!function_exists('ppress_can_bypass_maintenance')) {
    function ppress_can_bypass_maintenance(PDO $db, ?string $user): bool {
        if (!$user) return false;
        if (function_exists('ppc_user_can') && ppc_user_can('view_admin')) return true;
        try {
            $st = $db->prepare("SELECT 1 FROM user_roles WHERE username=:u AND role='admin' LIMIT 1");
            $st->execute([':u'=>$user]); return (bool)$st->fetchColumn();
        } catch (Throwable $t) { return false; }
    }
}
