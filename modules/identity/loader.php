<?php
// ============================================================================
// ProjectPlayCore – Identity Loader (erweitert)
// Pfad: /backend/modules/identity/loader.php
// Beschreibung:
//  - Verwaltet Pending-Registrierungen (JSON)
//  - Enthält ppc_identity_enabled(), um globalen Aktivierungsstatus zu prüfen
//  - Nutzt DB-Tabelle identity_settings (skey='enabled')
// ============================================================================

declare(strict_types=1);
require_once __DIR__ . '/../../core/init.php';

// --------------------------------------------------------------------------
// Basis-Konfiguration & Pfade
// --------------------------------------------------------------------------
if (!defined('PPC_CONFIG_LOADED')) {
    $root = dirname(__DIR__, 2);
    $cfg  = dirname($root) . '/config.php';
    if (is_file($cfg)) require_once $cfg;
}

require_once __DIR__ . '/../../core/utils.php';
require_once __DIR__ . '/../../database/db.php';

// --------------------------------------------------------------------------
// Identity Speicherstruktur (Pending)
// --------------------------------------------------------------------------
if (!function_exists('identity_dir')) {
    function identity_dir(): string {
        $dir = rtrim(PPC_STORAGE, '/') . '/modules/identity';
        @mkdir($dir, 0775, true);
        @mkdir($dir . '/pending', 0775, true);
        @mkdir($dir . '/logs', 0775, true);
        return $dir;
    }
}

if (!function_exists('identity_pending_path')) {
    function identity_pending_path(string $token): string {
        return identity_dir() . '/pending/' . $token . '.json';
    }
}

if (!function_exists('identity_pending_save')) {
    function identity_pending_save(array $pending): bool {
        $token = $pending['pending_id'] ?? null;
        if (!$token) return false;
        $file = identity_pending_path($token);
        $tmp  = $file . '.tmp';
        $json = json_encode($pending, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) return false;
        if (@file_put_contents($tmp, $json) === false) return false;
        return @rename($tmp, $file);
    }
}

if (!function_exists('identity_pending_load_by_verification')) {
    function identity_pending_load_by_verification(string $verification_id): ?array {
        foreach (glob(identity_dir() . '/pending/*.json') ?: [] as $f) {
            $raw = @file_get_contents($f);
            $data = json_decode($raw ?: 'null', true);
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
        $ts = strtotime($dob . ' 00:00:00');
        if ($ts === false) return 'unknown';
        $age = (int)floor((time() - $ts) / 31556952);
        if ($age < 14) return '<14';
        if ($age < 18) return '14-17';
        return '18+';
    }
}

// --------------------------------------------------------------------------
// Aktivierungsstatus des Identity-Systems (Feature-Flag)
// --------------------------------------------------------------------------
if (!function_exists('ppc_identity_enabled')) {
    /**
     * Prüft, ob Identity-System aktiv ist.
     * Gibt true zurück, wenn identity_settings.enabled = '1'
     * oder Tabelle nicht vorhanden (Default: aktiv).
     */
    function ppc_identity_enabled(): bool {
        try {
            $db = ppc_db();
            $db->exec("CREATE TABLE IF NOT EXISTS identity_settings (
                skey   VARCHAR(64) PRIMARY KEY,
                svalue TEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $st = $db->prepare("SELECT svalue FROM identity_settings WHERE skey='enabled' LIMIT 1");
            $st->execute();
            $val = $st->fetchColumn();

            // Default aktiv, falls kein Eintrag
            return ($val === false) ? true : ($val === '1');
        } catch (Throwable $t) {
            return true; // Fallback: aktiv, um Login nicht zu blockieren
        }
    }
}
