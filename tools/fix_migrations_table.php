<?php
declare(strict_types=1);

// @admin-button: Migrationstabelle Reparieren | /backend/tools/fix_migrations_table.php | System | 16
// ============================================================================
// ProjectPlayCore – Migrations-Tabelle Reparieren
// Pfad: /backend/tools/fix_migrations_table.php
// Beschreibung:
//  Bringt die Tabelle ppc_module_versions in einen sauberen, erwarteten Zustand.
//  - Legt Tabelle an, falls fehlt
//  - Erzwingt Spalten: module VARCHAR(64), name VARCHAR(190), applied_at TIMESTAMP
//  - Entfernt alte/kaputte Indizes (inkl. uq_module_version)
//  - Legt UNIQUE(module,name) an
//  - Löscht offensichtliche kaputte Zeilen (leerer name)
//  Danach: /backend/tools/run_migrations.php ausführen.
// ============================================================================

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../../config.php';

ppc_security_headers();
ppc_require_login();

$db = ppc_db();
header('Content-Type: text/html; charset=utf-8');

// ----------------------------------------------------------------------------
// Hilfsfunktionen
// ----------------------------------------------------------------------------
function colExists(PDO $db, string $table, string $col): bool {
  $st = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c LIMIT 1");
  $st->execute([':t' => $table, ':c' => $col]);
  return (bool)$st->fetchColumn();
}

function idxExists(PDO $db, string $table, string $idx): bool {
  $st = $db->prepare("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND INDEX_NAME=:i LIMIT 1");
  $st->execute([':t' => $table, ':i' => $idx]);
  return (bool)$st->fetchColumn();
}

// ----------------------------------------------------------------------------
// Hauptlogik
// ----------------------------------------------------------------------------
$log = [];
try {
  $db->exec("CREATE TABLE IF NOT EXISTS ppc_module_versions (
    module     VARCHAR(64)  NOT NULL,
    name       VARCHAR(190) NOT NULL,
    applied_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (module, name)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $log[] = "OK: Tabelle existiert oder wurde angelegt.";

  // Spalten sicherstellen
  if (!colExists($db, 'ppc_module_versions', 'module')) {
    $db->exec("ALTER TABLE ppc_module_versions ADD COLUMN module VARCHAR(64) NOT NULL");
    $log[] = "ALTER: Spalte module hinzugefügt.";
  }
  if (!colExists($db, 'ppc_module_versions', 'name')) {
    if (colExists($db, 'ppc_module_versions', 'filename')) {
      $db->exec("ALTER TABLE ppc_module_versions CHANGE COLUMN filename name VARCHAR(190) NOT NULL");
      $log[] = "ALTER: filename → name umbenannt.";
    } else {
      $db->exec("ALTER TABLE ppc_module_versions ADD COLUMN name VARCHAR(190) NOT NULL");
      $log[] = "ALTER: Spalte name hinzugefügt.";
    }
  }
  if (!colExists($db, 'ppc_module_versions', 'applied_at')) {
    $db->exec("ALTER TABLE ppc_module_versions ADD COLUMN applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
    $log[] = "ALTER: Spalte applied_at hinzugefügt.";
  }

  // Alte oder falsche Indizes entfernen
  foreach (['uq_module_version','uq_module_filename','uq_module_file'] as $oldIdx) {
    if (idxExists($db, 'ppc_module_versions', $oldIdx)) {
      $db->exec("ALTER TABLE ppc_module_versions DROP INDEX {$oldIdx}");
      $log[] = "DROP INDEX: {$oldIdx}";
    }
  }

  // Primary Key sicherstellen
  $db->exec("ALTER TABLE ppc_module_versions DROP PRIMARY KEY, ADD PRIMARY KEY (module, name)");
  $log[] = "PRIMARY KEY (module,name) neu gesetzt.";

  // Ungültige Zeilen löschen
  $del = $db->exec("DELETE FROM ppc_module_versions WHERE name = '' OR name IS NULL");
  if ($del !== false && $del > 0) $log[] = "CLEAN: {$del} fehlerhafte Zeilen entfernt (leerer name).";

  echo "<h1>Schema-Fix abgeschlossen</h1>";
  echo "<pre>".e(implode("\n", $log))."</pre>";
  echo '<p><a class="ppc-button" href="/backend/tools/run_migrations.php">Migrationen jetzt ausführen</a></p>';
  echo '<p><a class="ppc-button-secondary" href="/backend/tools/migrations_status.php">Status ansehen</a></p>';
} catch (Throwable $t) {
  http_response_code(500);
  echo "<h1>Fehler</h1><pre>".e($t->getMessage())."</pre>";
  echo "<p>Siehe Logs in /tmp/ppc_storage/logs/</p>";
}
