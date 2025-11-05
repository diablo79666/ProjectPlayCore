<?php
/**
 * Identity Loader – Storage-Helfer für Pending-Registrierungen & Finalisierung
 * Pending wird in PPC_STORAGE/modules/identity/pending/*.json gespeichert.
 * Finale Accounts werden in 'users' eingefügt (DOB nur per Webhook!).
 */
declare(strict_types=1);

if (!defined('PPC_CONFIG_LOADED')) {
    $root = dirname(__DIR__, 2);
    $cfg  = dirname($root) . '/config.php';
    if (is_file($cfg)) require_once $cfg;
}

if (!function_exists('identity_dir')) {
    function identity_dir(): string {
        $dir = rtrim(PPC_STORAGE,'/').'/modules/identity';
        @mkdir($dir, 0775, true);
        @mkdir($dir.'/pending', 0775, true);
        @mkdir($dir.'/logs', 0775, true);
        return $dir;
    }
}
if (!function_exists('identity_pending_path')) {
    function identity_pending_path(string $token): string {
        return identity_dir().'/pending/'.$token.'.json';
    }
}
if (!function_exists('identity_pending_save')) {
    function identity_pending_save(array $pending): bool {
        $token = $pending['pending_id'] ?? null;
        if (!$token) return false;
        $file = identity_pending_path($token);
        $tmp  = $file.'.tmp';
        $json = json_encode($pending, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        if ($json===false) return false;
        if (@file_put_contents($tmp, $json) === false) return false;
        return @rename($tmp, $file);
    }
}
if (!function_exists('identity_pending_load_by_verification')) {
    function identity_pending_load_by_verification(string $verification_id): ?array {
        foreach (glob(identity_dir().'/pending/*.json') ?: [] as $f) {
            $raw = @file_get_contents($f);
            $data = json_decode($raw?:'null', true);
            if (is_array($data) && ($data['verification_id'] ?? '') === $verification_id) {
                return $data;
            }
        }
        return null;
    }
}
if (!function_exists('identity_pending_delete')) {
    function identity_pending_delete(string $pending_id): void {
        $f = identity_pending_path($pending_id);
        if (is_file($f)) @unlink($f);
    }
}
if (!function_exists('identity_age_band_from_dob')) {
    function identity_age_band_from_dob(string $dob): string {
        // dob: YYYY-MM-DD
        $ts = strtotime($dob.' 00:00:00');
        if ($ts===false) return 'unknown';
        $age = (int)floor((time()-$ts)/31556952); // grob
        if ($age < 14) return '<14';
        if ($age < 18) return '14-17';
        return '18+';
    }
}
