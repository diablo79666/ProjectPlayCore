<?php
// ============================================================================
// ProjectPlayCore – Status Loader (dezentralisiert)
// Pfad: /backend/modules/status/loader.php
// ============================================================================
declare(strict_types=1);
require_once __DIR__ . '/../../core/init.php';

// ============================================================================
// Globale Helferfunktionen
// ============================================================================
if (!function_exists('ppc_status_safe_tail')) {
    /**
     * Liest die letzten N Zeilen einer Datei effizient ein.
     */
    function ppc_status_safe_tail(string $file, int $lines = 200): string {
        if (!is_file($file)) return "(Datei nicht gefunden: {$file})";
        $fp = @fopen($file, 'rb');
        if (!$fp) return "(Datei nicht lesbar: {$file})";
        $buffer = '';
        $pos = -1;
        $lineCount = 0;
        $stat = fstat($fp);
        $size = $stat['size'] ?? 0;
        if ($size === 0) { fclose($fp); return "(leer)"; }
        while (-$pos <= $size && $lineCount < $lines) {
            fseek($fp, $pos, SEEK_END);
            $char = fgetc($fp);
            if ($char === "\n") $lineCount++;
            $buffer = $char . $buffer;
            $pos--;
        }
        fclose($fp);
        return $buffer;
    }
}

// ============================================================================
// Container-Registrierung
// ============================================================================
use Core\Container;
Container::register('status', fn() => true);
