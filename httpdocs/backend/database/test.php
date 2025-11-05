<?php
// ============================================================================
// Datei: backend/database/test.php
// Beschreibung: Testet Verbindung, Tabellenzugriff & Schreibrechte
// ============================================================================
declare(strict_types=1);

require_once __DIR__ . '/db.php';

echo "<h2>🔍 Datenbank-Test – ProjectPlayCore</h2>";

try {
    $stmt = $pdo->query("SELECT DATABASE() AS db");
    $row = $stmt->fetch();
    echo "<p>✅ Verbindung erfolgreich zur Datenbank: <strong>{$row['db']}</strong></p>";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tables)) {
        echo "<p>⚠️ Keine Tabellen gefunden. Möglicherweise ist die Datenbank leer.</p>";
    } else {
        echo "<p>📋 Tabellen in der Datenbank:</p><ul>";
        foreach ($tables as $t) echo "<li>{$t}</li>";
        echo "</ul>";
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS ppc_test (id INT AUTO_INCREMENT PRIMARY KEY, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("INSERT INTO ppc_test VALUES ()");
    echo "<p>✍️ Schreibtest erfolgreich. ID = " . $pdo->lastInsertId() . "</p>";
    $pdo->exec("DROP TABLE ppc_test");
    echo "<p>🧹 Testdaten wieder gelöscht.</p>";
    echo "<p style='color:green'><strong>✅ Alles funktioniert korrekt!</strong></p>";
} catch (Throwable $e) {
    echo "<p style='color:red'><strong>❌ Fehler:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
