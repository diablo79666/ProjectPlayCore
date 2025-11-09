<?php
// ============================================================================
// ProjectPlayCore Web System – Grundgerüst v1.0
// Datei: backend/core/security.php
// Beschreibung: CSRF, Input-Sanitizing, Brute-Force- & Rate-Limit-Schutz
// Hinweis: Konfiguration via Konstanten in /httpdocs/config.php möglich
// ============================================================================

declare(strict_types=1);

// Basis laden
require_once dirname(__DIR__, 2) . '/config.php';
require_once __DIR__ . '/session.php'; // Session-Setup sicherstellen
require_once __DIR__ . '/utils.php';

// PPC_ROOT Fallback (falls in config.php nicht gesetzt)
if (!defined('PPC_ROOT')) {
    define('PPC_ROOT', dirname(__DIR__, 2));
}

// -------------------------------
// Konfiguration (überschreibbar in config.php)
// -------------------------------
if (!defined('PPC_CSRF_TOKEN_NAME'))    define('PPC_CSRF_TOKEN_NAME', '_ppc_csrf');
if (!defined('PPC_BRUTE_FORCE_MAX'))    define('PPC_BRUTE_FORCE_MAX', 8);         // Versuche
if (!defined('PPC_BRUTE_FORCE_TTL'))    define('PPC_BRUTE_FORCE_TTL', 300);       // Zeitfenster in Sekunden
if (!defined('PPC_BRUTE_FORCE_BLOCK'))  define('PPC_BRUTE_FORCE_BLOCK', 900);     // Sperrdauer in Sekunden
if (!defined('PPC_BRUTE_FORCE_STORAGE')) define('PPC_BRUTE_FORCE_STORAGE', PPC_ROOT . '/storage/attack_lock.json');

// Storage-Verzeichnis sicherstellen
(function () {
    $dir = dirname(PPC_BRUTE_FORCE_STORAGE);
    if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
})();

// -------------------------------
// CSRF: Token generieren & prüfen
// -------------------------------
function ppc_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    if (empty($_SESSION[PPC_CSRF_TOKEN_NAME])) {
        $_SESSION[PPC_CSRF_TOKEN_NAME] = bin2hex(random_bytes(24));
    }
    return $_SESSION[PPC_CSRF_TOKEN_NAME];
}

function ppc_csrf_field(): string
{
    $t = ppc_csrf_token();
    return '<input type="hidden" name="' . htmlspecialchars(PPC_CSRF_TOKEN_NAME, ENT_QUOTES) . '" value="' . htmlspecialchars($t, ENT_QUOTES) . '">';
}

function ppc_csrf_check(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $name   = PPC_CSRF_TOKEN_NAME;
    $sent   = $_REQUEST[$name] ?? null;
    $stored = $_SESSION[$name] ?? null;
    return is_string($sent) && is_string($stored) && hash_equals($stored, $sent);
}

// -------------------------------
// Input-Sanitizing (leichtgewicht)
// -------------------------------
function ppc_sanitize_string(string $s): string
{
    $s = trim($s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ppc_sanitize_html(string $s): string
{
    // Entfernt Scripts; erlaubt Basis-HTML
    $s = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $s);
    return $s;
}

// -------------------------------
// Simple Brute-Force Protection (IP-basiert, Dateispeicher)
// -------------------------------
function ppc_bruteforce_record_fail(string $ip = null): void
{
    $ip   = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $file = PPC_BRUTE_FORCE_STORAGE;
    $data = [];

    if (file_exists($file)) {
        $json = @file_get_contents($file);
        $data = $json ? (json_decode($json, true) ?? []) : [];
    }

    $now = time();

    // Cleanup expired blocks & alte Attempts
    foreach ($data as $k => $v) {
        if (!is_array($v)) { unset($data[$k]); continue; }
        if (!empty($v['blocked_until']) && $v['blocked_until'] > 0 && $v['blocked_until'] < $now) {
            unset($data[$k]);
            continue;
        }
        if (isset($v['attempts']) && is_array($v['attempts'])) {
            $v['attempts'] = array_values(array_filter($v['attempts'], fn($t) => ($t + PPC_BRUTE_FORCE_TTL) >= $now));
            $data[$k] = $v;
        }
    }

    if (!isset($data[$ip])) {
        $data[$ip] = ['attempts' => [], 'blocked_until' => 0];
    }
    $data[$ip]['attempts'][] = $now;

    // Schwellwert prüfen
    $attempts = array_filter($data[$ip]['attempts'], fn($t) => ($t + PPC_BRUTE_FORCE_TTL) >= $now);
    if (count($attempts) >= PPC_BRUTE_FORCE_MAX) {
        $data[$ip]['blocked_until'] = $now + PPC_BRUTE_FORCE_BLOCK;
    }

    @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function ppc_bruteforce_is_blocked(string $ip = null): bool
{
    $ip   = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $file = PPC_BRUTE_FORCE_STORAGE;
    if (!file_exists($file)) return false;

    $json = @file_get_contents($file);
    $data = $json ? (json_decode($json, true) ?? []) : [];
    if (!isset($data[$ip])) return false;

    $now = time();
    if (!empty($data[$ip]['blocked_until']) && $data[$ip]['blocked_until'] > $now) {
        return true;
    }

    // Alte Versuche ausmisten & speichern
    if (isset($data[$ip]['attempts']) && is_array($data[$ip]['attempts'])) {
        $data[$ip]['attempts'] = array_values(array_filter($data[$ip]['attempts'], fn($t) => ($t + PPC_BRUTE_FORCE_TTL) >= $now));
        @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    return false;
}

// -------------------------------
// Helper: clear record on success
// -------------------------------
function ppc_bruteforce_clear(string $ip = null): void
{
    $ip   = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $file = PPC_BRUTE_FORCE_STORAGE;
    if (!file_exists($file)) return;

    $json = @file_get_contents($file);
    $data = $json ? (json_decode($json, true) ?? []) : [];
    if (isset($data[$ip])) {
        unset($data[$ip]);
        @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

// -------------------------------
// Rate-Limit helper (per key)
// -------------------------------
function ppc_rate_limit(string $key, int $max = 100, int $ttl = 60): bool
{
    $store = PPC_ROOT . '/storage/rate_' . md5($key) . '.json';
    $dir   = dirname($store);
    if (!is_dir($dir)) { @mkdir($dir, 0777, true); }

    $now  = time();
    $data = [];

    if (file_exists($store)) {
        $data = json_decode((string)@file_get_contents($store), true) ?: [];
    }

    // Alte Einträge raus
    $data = array_values(array_filter($data, fn($t) => ($t + $ttl) >= $now));

    if (count($data) >= $max) {
        // trotzdem getrimmte Liste speichern
        @file_put_contents($store, json_encode($data, JSON_PRETTY_PRINT));
        return false;
    }

    $data[] = $now;
    @file_put_contents($store, json_encode($data, JSON_PRETTY_PRINT));
    return true;
}

// -------------------------------
// Sicherheits-Header (kann in Response genutzt werden)
// -------------------------------
function ppc_security_headers(): void
{
    // Iframe-Darstellung nur innerhalb der eigenen Domain erlauben
    // (Admin-Dashboard lädt Tools im Iframe → daher Ausnahme für index.php)
    if (basename($_SERVER['SCRIPT_NAME']) !== 'index.php') {
        header('X-Frame-Options: SAMEORIGIN');
    }

    // Standard-Sicherheitsheader
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');
}
