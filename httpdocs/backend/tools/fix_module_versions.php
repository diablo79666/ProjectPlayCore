<?php
/**
 * tools/fix_module_versions.php
 * Repariert ppc_module_versions:
 *  - legt Tabelle an (falls fehlt)
 *  - entfernt Legacy-Unique-Index 'uq_module_version'
 *  - setzt PRIMARY KEY(module)
 *  - dedupliziert Einträge
 *  - zeigt Ergebnis an
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../../config.php';

ppc_security_headers();
ppc_require_login();

$db = ppc_db();
$ops = [];

try {
    // Tabelle sicherstellen
    $db->exec("
        CREATE TABLE IF NOT EXISTS ppc_module_versions (
            module  VARCHAR(128) NOT NULL,
            version VARCHAR(32)  NOT NULL DEFAULT '',
            PRIMARY KEY (module)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $ops[] = "Tabelle ppc_module_versions OK/angelegt.";

    // Legacy-Index entfernen (best effort)
    try {
        $db->exec("ALTER TABLE ppc_module_versions DROP INDEX uq_module_version");
        $ops[] = "Legacy-Index uq_module_version entfernt.";
    } catch (Throwable $e) {
        $ops[] = "Legacy-Index uq_module_version nicht vorhanden (OK).";
    }

    // sicherstellen: Primary Key nur auf (module)
    // (Falls Alt-PK anders war, wird das hier best-effort korrigiert.)
    // Hinweis: MySQL erlaubt nicht „change PK if exists“ bedingungslos.
    // Wir prüfen über INFORMATION_SCHEMA, ob module bereits PK ist.
    $stmt = $db->query("
        SELECT COUNT(*) 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'ppc_module_versions'
          AND CONSTRAINT_NAME = 'PRIMARY'
          AND COLUMN_NAME = 'module'
    ");
    $isPkModule = (bool)$stmt->fetchColumn();
    if (!$isPkModule) {
        try { $db->exec("ALTER TABLE ppc_module_versions DROP PRIMARY KEY"); } catch (Throwable $e) {}
        $db->exec("ALTER TABLE ppc_module_versions ADD PRIMARY KEY (module)");
        $ops[] = "PRIMARY KEY(module) gesetzt.";
    } else {
        $ops[] = "PRIMARY KEY(module) bereits vorhanden.";
    }

    // Deduplizieren (falls Alt-Design (module,version) unique → evtl. Duplikate übrig)
    // Wir behalten nur den neuesten Eintrag je module (naiver Ansatz über created_at existiert hier nicht,
    // daher nehmen wir den mit der höchsten version numerisch/lexikografisch als „neu“).
    // Wenn keine Duplikate existieren, passiert nichts.
    $db->beginTransaction();
    $dups = $db->query("
        SELECT module, COUNT(*) c FROM ppc_module_versions GROUP BY module HAVING c > 1
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($dups as $d) {
        $mod = (string)$d['module'];
        $rows = $db->prepare("SELECT module, version FROM ppc_module_versions WHERE module=:m ORDER BY version DESC");
        $rows->execute([':m'=>$mod]);
        $all = $rows->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $keepVersion = $all[0]['version'] ?? '';
        // Löschen aller außer der „keepVersion“
        $del = $db->prepare("DELETE FROM ppc_module_versions WHERE module=:m AND version<>:v");
        $del->execute([':m'=>$mod, ':v'=>$keepVersion]);
        $ops[] = "Duplikate für {$mod} entfernt, behalten: {$keepVersion}";
    }
    $db->commit();

    // Test-UPSERTs (ungefähr wie unsere Migrationen später)
    $up = $db->prepare("
        INSERT INTO ppc_module_versions(module, version)
        VALUES(:m,:v)
        ON DUPLICATE KEY UPDATE version=VALUES(version)
    ");
    $up->execute([':m'=>'identity',         ':v'=>'0.2.0']);
    $up->execute([':m'=>'projectplaypress', ':v'=>'1.1.0']);
    $ops[] = "Test-UPSERTs OK (identity, projectplaypress).";

    $status = "OK – Reparatur abgeschlossen.";
} catch (Throwable $t) {
    http_response_code(500);
    $status = "FEHLER: " . $t->getMessage();
}

?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Fix Module Versions</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="ppc-container" style="max-width:900px;margin:2rem auto">
  <div class="ppc-card">
    <h1>ppc_module_versions – Reparatur</h1>
    <p><strong><?=e($status)?></strong></p>
    <ul>
      <?php foreach ($ops as $o): ?><li><?=e($o)?></li><?php endforeach; ?>
    </ul>
    <p><a class="ppc-button" href="/backend/tools/run_migrations.php?mode=run">Migrationen jetzt ausführen</a></p>
    <p><a class="ppc-button-secondary" href="/backend/">Zum Admin-Dashboard</a></p>
  </div>
</body>
</html>
