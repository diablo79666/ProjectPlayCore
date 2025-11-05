<?php
/**
 * ProjectPlayCore – Admin-Buttons Migration (einsatzfähig)
 * - Erstellt die Tabelle admin_buttons (falls fehlt)
 * - Rüstet Container-Felder nach (source_type, source_id, last_seen, health)
 * - Legt sinnvolle Indizes & Constraints an (idempotent)
 * Aufruf (nur eingeloggt, Admin/Caps): /backend/tools/admin_buttons_migration.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../../config.php';

ppc_security_headers();
ppc_require_login();

$db   = ppc_db();
$user = (string)(ppc_current_user() ?? '');

/* --- Rechteprüfung: view_admin ODER Rolle admin/superadmin ----------------- */
$allowed = false;
try {
    if (function_exists('ppc_user_can') && ppc_user_can('view_admin', $user)) $allowed = true;
    if (!$allowed && function_exists('ppc_has_role') && (ppc_has_role('admin') || ppc_has_role('superadmin'))) $allowed = true;
    if (!$allowed) {
        $st = $db->prepare("SELECT 1 FROM user_roles WHERE username=:u AND role IN ('admin','superadmin') LIMIT 1");
        $st->execute([':u'=>$user]);
        if ($st->fetchColumn()) $allowed = true;
    }
} catch (Throwable $t) { /* ignore */ }

if (!$allowed) {
    http_response_code(403);
    exit('403 – view_admin erforderlich');
}

/* ------------------------------ Helpers ----------------------------------- */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$log = [];
function step(&$log, string $msg){ $log[] = $msg; }

/* ------------------------------ Migration --------------------------------- */
try {
    // Tabelle erstellen, falls nicht vorhanden
    $db->exec("
        CREATE TABLE IF NOT EXISTS admin_buttons (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            area VARCHAR(20) NOT NULL DEFAULT 'dashboard',       -- dashboard|navbar|footer
            title VARCHAR(190) NOT NULL,
            href VARCHAR(255) NOT NULL,
            group_name VARCHAR(100) NULL,
            sort_order INT NOT NULL DEFAULT 1000,
            required_cap VARCHAR(64) NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            -- Container-Felder:
            source_type VARCHAR(16) NULL,                        -- 'container'|'local'|NULL
            source_id   VARCHAR(64) NULL,                        -- Service/Container-Name
            last_seen   TIMESTAMP NULL DEFAULT NULL,
            health      VARCHAR(8) NULL,                         -- ok|warn|err|NULL
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    step($log, "✅ Tabelle 'admin_buttons' vorhanden/erstellt.");

    // Spaltenliste holen
    $cols = [];
    $q = $db->query("SHOW COLUMNS FROM admin_buttons");
    if ($q) {
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $c) $cols[strtolower($c['Field'])] = true;
    }

    // Nachrüsten (idempotent)
    $alter = [];
    if (!isset($cols['area']))         $alter[] = "ADD COLUMN area VARCHAR(20) NOT NULL DEFAULT 'dashboard' AFTER id";
    if (!isset($cols['title']))        $alter[] = "ADD COLUMN title VARCHAR(190) NOT NULL";
    if (!isset($cols['href']))         $alter[] = "ADD COLUMN href VARCHAR(255) NOT NULL";
    if (!isset($cols['group_name']))   $alter[] = "ADD COLUMN group_name VARCHAR(100) NULL";
    if (!isset($cols['sort_order']))   $alter[] = "ADD COLUMN sort_order INT NOT NULL DEFAULT 1000";
    if (!isset($cols['required_cap'])) $alter[] = "ADD COLUMN required_cap VARCHAR(64) NULL";
    if (!isset($cols['enabled']))      $alter[] = "ADD COLUMN enabled TINYINT(1) NOT NULL DEFAULT 1";

    if (!isset($cols['source_type']))  $alter[] = "ADD COLUMN source_type VARCHAR(16) NULL";
    if (!isset($cols['source_id']))    $alter[] = "ADD COLUMN source_id VARCHAR(64) NULL";
    if (!isset($cols['last_seen']))    $alter[] = "ADD COLUMN last_seen TIMESTAMP NULL DEFAULT NULL";
    if (!isset($cols['health']))       $alter[] = "ADD COLUMN health VARCHAR(8) NULL";

    if (!isset($cols['created_at']))   $alter[] = "ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP";
    if (!isset($cols['updated_at']))   $alter[] = "ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";

    if ($alter) {
        $db->exec("ALTER TABLE admin_buttons " . implode(", ", $alter));
        step($log, "🔧 Spalten nachgerüstet: ".count($alter));
    } else {
        step($log, "ℹ️ Spalten bereits vollständig vorhanden.");
    }

    // Indizes/Constraints idempotent anlegen
    $indexes = [];
    $qi = $db->query("SHOW INDEX FROM admin_buttons");
    if ($qi) {
        foreach ($qi->fetchAll(PDO::FETCH_ASSOC) as $i) {
            $indexes[strtolower($i['Key_name'])] = true;
        }
    }

    // Eindeutiger Key pro Zielbereich/Link
    if (!isset($indexes['uniq_area_href'])) {
        $db->exec("ALTER TABLE admin_buttons ADD UNIQUE KEY uniq_area_href (area, href)");
        step($log, "🧩 Index uniq_area_href angelegt.");
    } else {
        step($log, "ℹ️ Index uniq_area_href existiert bereits.");
    }

    // Hilfsindizes
    if (!isset($indexes['idx_area'])) {
        $db->exec("ALTER TABLE admin_buttons ADD INDEX idx_area (area)");
        step($log, "🧩 Index idx_area angelegt.");
    }
    if (!isset($indexes['idx_group'])) {
        $db->exec("ALTER TABLE admin_buttons ADD INDEX idx_group (group_name)");
        step($log, "🧩 Index idx_group angelegt.");
    }
    if (!isset($indexes['idx_source'])) {
        $db->exec("ALTER TABLE admin_buttons ADD INDEX idx_source (source_type, source_id)");
        step($log, "🧩 Index idx_source angelegt.");
    }
    if (!isset($indexes['idx_enabled'])) {
        $db->exec("ALTER TABLE admin_buttons ADD INDEX idx_enabled (enabled)");
        step($log, "🧩 Index idx_enabled angelegt.");
    }

    $ok = true;
} catch (Throwable $t) {
    $ok = false;
    $err = $t->getMessage();
}

/* -------------------------------- Output ---------------------------------- */
header('Content-Type: text/html; charset=utf-8'); ?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Admin-Buttons Migration – ProjectPlayCore</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
.wrap{max-width:980px;margin:2rem auto;padding:1rem}
.card{border:1px solid #222;border-radius:12px;padding:14px;background:#0c0c0c;margin-bottom:12px}
.ok{color:#9fe3bd}.err{color:#f3a1a1}.mono{font-family:ui-monospace,SFMono-Regular,Consolas,monospace}
</style>
</head>
<body class="ppc-container">
<div class="wrap">
  <div class="card">
    <h1>Admin-Buttons Migration</h1>
    <p>Status: <?= $ok ? '<span class="ok">OK</span>' : '<span class="err">FEHLER</span>' ?></p>
    <?php if(!$ok): ?>
      <p class="err mono"><?= e($err??'Unbekannter Fehler') ?></p>
    <?php endif; ?>
    <?php if(!empty($log)): ?>
      <ul><?php foreach($log as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.6rem">
      <a class="ppc-button-secondary" href="/backend/">Zum Admin-Dashboard</a>
      <a class="ppc-button-secondary" href="/backend/tools/containers_sync.php">Container synchronisieren</a>
    </div>
  </div>
</div>
</body>
</html>
