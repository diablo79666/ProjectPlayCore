<?php
// ============================================================================
// Modul: hello – Loader
// ============================================================================
declare(strict_types=1);

// (Optional) Beim Laden ins Log schreiben
$logDir = dirname(__DIR__, 2) . '/storage/logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0777, true); }
@file_put_contents($logDir . '/modules.log', '[' . date('c') . "] Modul 'hello' geladen.\n", FILE_APPEND);

// Hier könnten Hook-Registrierungen stehen (später)
