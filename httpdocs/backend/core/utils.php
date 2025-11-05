<?php
/**
 * ProjectPlayCore – Core Utils
 * PHP 8.2
 * Enthält: ppc_redirect, ppc_json, ppc_clean, e, ppc_render, ppc_debug
 * Idempotent: Mehrfaches Einbinden ist erlaubt.
 */

declare(strict_types=1);

// ==========================================
// Include-Guard
// ==========================================
if (defined('PPC_UTILS_LOADED')) {
    return;
}
define('PPC_UTILS_LOADED', true);

// ==========================================
// config.php robust laden
// ==========================================
if (!defined('PPC_CONFIG_LOADED')) {
    $root = dirname(__DIR__, 2); // /httpdocs
    $cfg  = $root . '/config.php';
    if (!is_file($cfg)) {
        $doc = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
        if ($doc && is_file($doc . '/config.php')) {
            $cfg = $doc . '/config.php';
        }
    }
    if (is_file($cfg)) {
        require_once $cfg;
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(500);
        echo "FATAL: config.php nicht gefunden (utils).";
        exit;
    }
}

// ==========================================
// HTTP Redirect
// ==========================================
if (!function_exists('ppc_redirect')) {
    function ppc_redirect(string $location, int $code = 302): void {
        if (!headers_sent()) {
            header('Location: ' . $location, true, $code);
        }
        echo '<!doctype html><meta http-equiv="refresh" content="0;url='
            . htmlspecialchars($location, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
        exit;
    }
}

// ==========================================
// JSON-Ausgabe mit Statuscode
// ==========================================
if (!function_exists('ppc_json')) {
    function ppc_json($data, int $status = 200): void {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ==========================================
// Eingabe säubern
// ==========================================
if (!function_exists('ppc_clean')) {
    function ppc_clean(?string $value): string {
        return trim((string)($value ?? ''));
    }
}

// ==========================================
// HTML-Escaping
// ==========================================
if (!function_exists('e')) {
    function e(?string $value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// ==========================================
// Einfacher Template-Loader
// ==========================================
if (!function_exists('ppc_render')) {
    function ppc_render(string $relativePath, array $vars = []): void {
        $root = rtrim(PPC_ROOT ?? (__DIR__ . '/../../..'), '/');
        $file = $root . '/' . ltrim($relativePath, '/');
        if (!is_file($file)) {
            http_response_code(404);
            echo "Template nicht gefunden: " . e($relativePath);
            exit;
        }
        extract($vars, EXTR_OVERWRITE);
        require $file;
        exit;
    }
}

// ==========================================
// Entwicklungs-Debug-Ausgabe (nur im DEV)
// ==========================================
if (!function_exists('ppc_debug')) {
    function ppc_debug(...$vars): void {
        if (defined('PPC_ENV') && PPC_ENV === 'dev') {
            if (!headers_sent()) {
                header('Content-Type: text/plain; charset=utf-8');
            }
            foreach ($vars as $v) {
                var_dump($v);
            }
            exit;
        }
    }
}
