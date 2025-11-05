<?php
// PPC Storage Repair (einmalig ausführen, dann löschen)
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = __DIR__;
$dirs = [
    $root . '/storage',
    $root . '/storage/sessions',
    $root . '/storage/logs',
    $root . '/storage/modules',
];

$ok = true;
foreach ($dirs as $d) {
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }
    if (!is_dir($d)) {
        echo "<p><b>FEHLER:</b> Konnte Ordner nicht anlegen: {$d}</p>";
        $ok = false;
    } else {
        @chmod($d, 0775);
        // Kleiner Schutz & Index
        @file_put_contents($d . '/index.html', '');
        @file_put_contents($d . '/.htaccess', "Options -Indexes\n");
        echo "<p>OK: {$d}</p>";
    }
}

// Schreibtest
if ($ok) {
    $test = $root . '/storage/logs/_writetest.log';
    $w = @file_put_contents($test, '[' . date('c') . "] repair-write\n", FILE_APPEND);
    if ($w === false) {
        echo "<p><b>FEHLER:</b> Schreibtest fehlgeschlagen: {$test}</p>";
    } else {
        echo "<p>Schreibtest OK: {$test}</p>";
    }
}

echo "<hr><p>Fertig. Bitte <b>_repair_storage.php</b> danach löschen.</p>";
