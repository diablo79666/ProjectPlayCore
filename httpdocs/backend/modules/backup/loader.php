<?php
/**
 * Module: backup – Loader
 * v1: JSON-basiert, keine DB-Tabellen. Stellt nur Logging/Settings-Helper bereit.
 */
declare(strict_types=1);

// Basiskonstanten laden, falls direkt aufgerufen
if (!defined('PPC_CONFIG_LOADED')) {
    $root = dirname(__DIR__, 2); // /httpdocs/backend
    $cfg  = dirname($root) . '/config.php';
    if (is_file($cfg)) require_once $cfg;
}

if (!function_exists('backup_log')) {
    function backup_log(string $msg): void {
        $dir = PPC_STORAGE . '/logs';
        @mkdir($dir, 0775, true);
        @file_put_contents($dir.'/modules.log', '['.date('c')."] [backup] {$msg}\n", FILE_APPEND);
    }
}

if (!function_exists('backup_settings_path')) {
    function backup_settings_path(): string {
        $dir = PPC_STORAGE . '/modules';
        @mkdir($dir, 0775, true);
        return $dir . '/backup.json';
    }
}

if (!function_exists('backup_settings_load')) {
    function backup_settings_load(): array {
        $file = backup_settings_path();
        if (!is_file($file)) {
            return [
                'mode' => 'manual',              // manual | auto | time | disabled
                'auto_interval_minutes' => 120,  // nur für mode=auto
                'time_daily' => '03:30',         // nur für mode=time (HH:MM, 24h)
                'warnings_minutes' => [30,15,5,1],
                'notes' => ''
            ];
        }
        $raw = @file_get_contents($file);
        $data = json_decode($raw ?: 'null', true);
        if (!is_array($data)) return backup_settings_load_defaults();
        // sanft mergen mit Defaults
        $def = backup_settings_load_defaults();
        return array_replace($def, $data);
    }
}

if (!function_exists('backup_settings_load_defaults')) {
    function backup_settings_load_defaults(): array {
        return [
            'mode' => 'manual',
            'auto_interval_minutes' => 120,
            'time_daily' => '03:30',
            'warnings_minutes' => [30,15,5,1],
            'notes' => ''
        ];
    }
}

if (!function_exists('backup_settings_save')) {
    function backup_settings_save(array $cfg): bool {
        $file = backup_settings_path();
        $json = json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) return false;
        $tmp = $file.'.tmp';
        if (@file_put_contents($tmp, $json) === false) return false;
        return @rename($tmp, $file);
    }
}
