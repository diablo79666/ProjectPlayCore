<?php
// ============================================================================
// Pfad: backend/database/db.php
// Beschreibung: Zentrale PDO-Datenbankverbindung (Singleton)
// Änderung: Bei Verbindungsfehler KEIN exit/HTTP 500 -> Exception werfen,
//           damit /user/login.php sauber auf Demo-Fallback wechseln kann.
// ============================================================================
declare(strict_types=1);

// Feste Einwahldaten
const PPC_DB_HOST = 'localhost';
const PPC_DB_NAME = 'ProjectPlay_Website';
const PPC_DB_USER = 'Admin';
const PPC_DB_PASS = 'Jj#1058094197';
const PPC_DB_PORT = 3306;

/** Liefert eine PDO-Instanz (Singleton). */
function ppc_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = 'mysql:host='.PPC_DB_HOST.';dbname='.PPC_DB_NAME.';charset=utf8mb4;port='.(int)PPC_DB_PORT;

    try {
        $pdo = new PDO($dsn, PPC_DB_USER, PPC_DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return $pdo;
    } catch (Throwable $e) {
        // Loggen statt Seite hart zu beenden
        $root   = dirname(__DIR__, 2);
        $logDir = $root . '/storage/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
        @file_put_contents($logDir.'/db_error.log', '['.date('c').'] '.$e->getMessage()." | DSN={$dsn}\n", FILE_APPEND);

        // WICHTIG: Exception werfen -> /user/login.php fängt das ab (Demo-Fallback)
        throw new RuntimeException('Database unavailable');
    }
}


