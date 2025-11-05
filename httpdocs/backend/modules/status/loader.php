<?php
/**
 * Module: status – Loader (leichtgewichtig)
 * Keine Tabellen nötig. Stellt nur optional kleine Helfer bereit.
 */
declare(strict_types=1);

// Basiskonstanten einbinden, falls direkt geladen
if (!defined('PPC_CONFIG_LOADED')) {
    $root = dirname(__DIR__, 2);        // /httpdocs/backend
    $cfg  = dirname($root) . '/config.php';
    if (is_file($cfg)) require_once $cfg;
}

if (!function_exists('ppc_status_safe_tail')) {
    /**
     * Letzte N Zeilen aus Datei lesen (ohne viel RAM zu verbrauchen).
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
