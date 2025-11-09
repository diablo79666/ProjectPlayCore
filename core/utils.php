<?php
// ============================================================================
// ProjectPlayCore – Core Utils (bereinigt)
// Pfad: /backend/core/utils.php
// Beschreibung:
//  Basisfunktionen und Hilfsmethoden für das gesamte Backend.
//  Lädt automatisch den zentralen Container.
// ============================================================================

declare(strict_types=1);

require_once __DIR__ . '/container.php';

// ---------------------------------------------------------------------------
// HTML/Escape
// ---------------------------------------------------------------------------

if (!function_exists('e')) {
    function e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// ---------------------------------------------------------------------------
// Weiterleitungen
// ---------------------------------------------------------------------------

if (!function_exists('ppc_redirect')) {
    function ppc_redirect(string $url): never {
        header('Location: ' . $url);
        exit;
    }
}

// ---------------------------------------------------------------------------
// User / Session-Helfer
// ---------------------------------------------------------------------------

if (!function_exists('ppc_current_user')) {
    function ppc_current_user(): ?string {
        return $_SESSION['ppc_user'] ?? null;
    }
}

if (!function_exists('ppc_require_login')) {
    function ppc_require_login(): void {
        if (empty($_SESSION['ppc_user'])) {
            header('Location: /user/login.php');
            exit;
        }
    }
}

// ---------------------------------------------------------------------------
// JSON-Tools
// ---------------------------------------------------------------------------

if (!function_exists('ppc_json_load')) {
    function ppc_json_load(string $path): ?array {
        if (!is_file($path)) return null;
        $raw = @file_get_contents($path);
        return json_decode($raw ?: 'null', true);
    }
}

if (!function_exists('ppc_json_save')) {
    function ppc_json_save(string $path, array $data): bool {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) return false;
        $tmp = $path . '.tmp';
        if (@file_put_contents($tmp, $json) === false) return false;
        return @rename($tmp, $path);
    }
}
