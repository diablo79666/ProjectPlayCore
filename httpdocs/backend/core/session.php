<?php
/**
 * ProjectPlayCore – session.php
 * PHP-Sessions: Save-Path = PPC_STORAGE/sessions (auto), sichere Cookie-Parameter, Regeneration.
 */

declare(strict_types=1);

// ---------------------------------------------------------
// config.php robust laden (unabhängig vom include-Kontext)
// ---------------------------------------------------------
if (!defined('PPC_CONFIG_LOADED')) {
    // /backend/core → zwei Ebenen hoch = /httpdocs
    $root = dirname(__DIR__, 2);
    $cfg  = $root . '/config.php';

    if (!is_file($cfg)) {
        // Versuch über DocumentRoot
        $doc = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
        if ($doc && is_file($doc . '/config.php')) {
            $cfg = $doc . '/config.php';
        }
    }

    if (is_file($cfg)) {
        require_once $cfg;
    } else {
        // Harte Notbremse mit klarer Fehlermeldung
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(500);
        echo "FATAL: config.php nicht gefunden (gesucht unter: {$root}/config.php).";
        exit;
    }
}

// ---------------------------------------------------------
// Ziel-Speicherort für Sessions vorbereiten
// ---------------------------------------------------------
$sessionDir = PPC_STORAGE . '/sessions';
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0775, true);
}

if (!is_writable($sessionDir)) {
    // Fallback in System-Temp
    $sessionDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/ppc_sessions';
    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0775, true);
    }
}

// Save-Path setzen
@session_save_path($sessionDir);

// Sichere Cookie-Parameter
$secure   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$domain   = $_SERVER['HTTP_HOST'] ?? '';
$cookieParams = [
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => $domain,
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
];
@session_set_cookie_params($cookieParams);

// Session starten
@session_name('ppc_sid');
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// ---------------------------------------------------------
// Helper
// ---------------------------------------------------------
if (!function_exists('ppc_session_regenerate')) {
    function ppc_session_regenerate(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }
    }
}

if (!function_exists('ppc_current_user')) {
    function ppc_current_user(): ?string {
        return isset($_SESSION['user']) ? (string)$_SESSION['user'] : null;
    }
}

if (!function_exists('ppc_require_login')) {
    function ppc_require_login(): void {
        if (!ppc_current_user()) {
            if (!headers_sent()) {
                http_response_code(302);
                header('Location: /user/login.php');
            }
            echo '<!doctype html><meta http-equiv="refresh" content="0;url=/user/login.php">';
            exit;
        }
    }
}
