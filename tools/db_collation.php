<?php
/**
 * ProjectPlayCore – DB-Collation-Helfer (Admin-Tool)
 * Zweck:
 *   – Vorschau (Preview) und Ausführung (Apply) der Vereinheitlichung auf:
 *       * Zeichensatz: utf8mb4
 *       * Collation:   utf8mb4_unicode_ci
 *   – Idempotent, sicher, mit Rechteprüfung (view_admin oder DB-Rolle admin)
 *   – Keine Änderungen am Grundgerüst; reine Tools-Seite
 *
 * Aufruf (klickbar, sofern eingeloggt + Berechtigungen):
 *   /backend/tools/db_collation.php?action=preview
 *   /backend/tools/db_collation.php?action=apply
 *   Optional: &scope=core | all   (Default: core)
 *
 * "core" umfasst:
 *   users, parents, children, child_policies, child_audit,
 *   roles, role_caps, user_roles, modules, ppc_migrations
 *
 * "all" = alle InnoDB-Tabellen des aktuellen Schemas
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../../config.php';

ppc_security_headers();
ppc_require_login();

/* -------------------------------------------------------------------------- */
/* Rechteprüfung: view_admin ODER DB-Rolle admin                               */
/* -------------------------------------------------------------------------- */
$db   = ppc_db();
$user = (string)(ppc_current_user() ?? '');

$allowed = false;
try {
    if (function_exists('ppc_user_can') && ppc_user_can('view_admin', $user)) $allowed = true;
    if (!$allowed) {
        $st = $db->prepare("SELECT 1 FROM user_roles WHERE username=:u AND role='admin' LIMIT 1");
        $st->execute([':u'=>$user]);
        if ($st->fetchColumn()) $allowed = true;
    }
} catch (Throwable $t) { /* ignore */ }

if (!$allowed) {
    http_response_code(403);
    die('403 – view_admin erforderlich');
}

/* -------------------------------------------------------------------------- */
/* CSRF-Token (einfach, cookie-basiert)                                        */
/* -------------------------------------------------------------------------- */
$csrfCookie = 'dbcoll_csrf';
if (empty($_COOKIE[$csrfCookie])) {
    $token = bin2hex(random_bytes(16));
    setcookie($csrfCookie, $token, [
        'expires'=> time()+3600, 'path'=>'/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'),
        'httponly'=>false, 'samesite'=>'Lax',
    ]);
} else {
    $token = (string)$_COOKIE[$csrfCookie];
}
function csrf_ok(string $paramName='dbcoll_csrf'): bool {
    $cookie = (string)($_COOKIE['dbcoll_csrf'] ?? '');
    $field  = (string)($_POST[$paramName] ?? $_GET[$paramName] ?? '');
    return $cookie && $field && hash_equals($cookie, $field);
}

/* -------------------------------------------------------------------------- */
/* Einstellungen & Helper                                                      */
/* -------------------------------------------------------------------------- */
const TARGET_CHARSET  = 'utf8mb4';
const TARGET_COLLATE  = 'utf8mb4_unicode_ci';

$scope  = strtolower((string)($_GET['scope'] ?? 'core'));   // core|all
$action = strtolower((string)($_GET['action'] ?? 'preview')); // preview|apply

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function log_tool(string $msg): void {
    $dir = rtrim(PPC_STORAGE, '/').'/logs';
    @mkdir($dir, 0775, true);
    @file_put_contents($dir.'/db_collation.log', '['.date('c')."] ".$msg.PHP_EOL, FILE_APPEND);
}

function current_db_name(PDO $db): string {
    return (string)$db->query("SELECT DATABASE()")->fetchColumn();
}

/** Liste aller Tabellen im aktuellen Schema (optional gefiltert) */
function list_tables(PDO $db, string $scope): array {
    $schema = current_db_name($db);
    $tables = [];
    $sql = "SELECT TABLE_NAME, ENGINE
              FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = :s";
    $st = $db->prepare($sql);
    $st->execute([':s'=>$schema]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if ($scope === 'core') {
        $core = [
            'users','parents','children','child_policies','child_audit',
            'roles','role_caps','user_roles',
            'modules','ppc_migrations'
        ];
        foreach ($rows as $r) {
            $t = (string)$r['TABLE_NAME'];
            if (in_array($t, $core, true)) $tables[] = $t;
        }
    } else {
        // all: nur InnoDB-Tabellen (sinnvoll für Online-Konvertierung)
        foreach ($rows as $r) {
            if (strcasecmp((string)$r['ENGINE'], 'InnoDB')===0) {
                $tables[] = (string)$r['TABLE_NAME'];
            }
        }
        sort($tables, SORT_NATURAL | SORT_FLAG_CASE);
    }
    return $tables;
}

/** Liest für Anzeige die aktuelle Collation/Charset von DB & Tabellen */
function fetch_status(PDO $db, array $tables): array {
    $schema = current_db_name($db);
    $out = ['db'=>['default_collation'=>null,'default_charset'=>null],'tables'=>[]];

    try {
        $st = $db->prepare("SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
                              FROM information_schema.SCHEMATA
                             WHERE SCHEMA_NAME=:s");
        $st->execute([':s'=>$schema]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $out['db']['default_charset']   = $row['DEFAULT_CHARACTER_SET_NAME'] ?? null;
        $out['db']['default_collation'] = $row['DEFAULT_COLLATION_NAME'] ?? null;
    } catch (Throwable $t) {}

    // Tabellenstatus
    $sql = "SELECT TABLE_NAME, TABLE_COLLATION
              FROM information_schema.TABLES
             WHERE TABLE_SCHEMA=:s AND TABLE_NAME=:t";
    $st = $db->prepare($sql);
    foreach ($tables as $t) {
        try {
            $st->execute([':s'=>$schema, ':t'=>$t]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $out['tables'][$t] = [
                'table_collation' => $row['TABLE_COLLATION'] ?? null,
            ];
        } catch (Throwable $tt) {
            $out['tables'][$t] = ['table_collation'=>null];
        }
    }
    return $out;
}

/** Baut die geplanten SQL-Statements (Preview) */
function build_plan(PDO $db, array $tables): array {
    $plan = [];
    $schema = current_db_name($db);
    // ALTER DATABASE
    $plan[] = "ALTER DATABASE `{$schema}` CHARACTER SET ".TARGET_CHARSET." COLLATE ".TARGET_COLLATE;

    // Für jede Tabelle: CONVERT TO CHARACTER SET …
    foreach ($tables as $t) {
        $plan[] = "ALTER TABLE `{$t}` CONVERT TO CHARACTER SET ".TARGET_CHARSET." COLLATE ".TARGET_COLLATE;
    }
    return $plan;
}

/** Führt Plan aus (best-effort, einzelne Fehler protokollieren) */
function apply_plan(PDO $db, array $plan): array {
    $res = [];
    foreach ($plan as $sql) {
        try {
            $db->exec($sql);
            $res[] = ['sql'=>$sql,'ok'=>true,'err'=>null];
        } catch (Throwable $t) {
            $res[] = ['sql'=>$sql,'ok'=>false,'err'=>$t->getMessage()];
            log_tool("APPLY ERROR: ".$t->getMessage()." | SQL=".$sql);
        }
    }
    return $res;
}

/* -------------------------------------------------------------------------- */
/* Controller-Logik                                                            */
/* -------------------------------------------------------------------------- */
$tables   = list_tables($db, $scope);
$status   = fetch_status($db, $tables);
$plan     = build_plan($db, $tables);
$results  = null;
$msg_ok   = '';
$msg_err  = '';

if ($action === 'apply') {
    if (!csrf_ok('dbcoll_csrf')) {
        http_response_code(403);
        $msg_err = 'CSRF-Überprüfung fehlgeschlagen.';
    } else {
        $results = apply_plan($db, $plan);
        $okCount = count(array_filter($results, fn($r)=>$r['ok']));
        $errCnt  = count($results) - $okCount;
        $msg_ok  = "Ausgeführt: {$okCount} OK, {$errCnt} Fehler";
        // Status nach Apply neu einlesen (für Anzeige)
        $status  = fetch_status($db, $tables);
    }
}

/* -------------------------------------------------------------------------- */
/* Ausgabe (kompakte Admin-Seite im Dark-Theme)                                */
/* -------------------------------------------------------------------------- */
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>PPC Tools – DB Collation vereinheitlichen</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
.wrap{max-width:1100px;margin:2rem auto;padding:1rem}
.card{border:1px solid #222;border-radius:12px;padding:14px;background:#0c0c0c;margin-bottom:12px}
.row{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.5rem}
.flash{margin:.6rem 0;padding:.7rem .9rem;border-radius:8px}
.flash.ok{background:#e7f7ee;border:1px solid #6cc799;color:#103d24}
.flash.err{background:#fdecea;border:1px solid #f1998e;color:#5c1712}
table{width:100%;border-collapse:collapse}
th,td{padding:.5rem;border-bottom:1px solid #222;text-align:left}
.badge{display:inline-block;padding:.15rem .5rem;border:1px solid #555;border-radius:999px;font-size:.85rem;margin-left:.4rem}
small{color:#999}
</style>
</head>
<body class="ppc-container">
<div class="wrap">
  <div style="display:flex;justify-content:space-between;align-items:center">
    <div>
      <h1>DB-Collation vereinheitlichen</h1>
      <div class="muted">Angemeldet als: <strong><?= e($user) ?></strong></div>
    </div>
    <div class="row">
      <a class="ppc-button-secondary" href="/backend/">Admin-Dashboard</a>
      <a class="ppc-button-secondary" href="/backend/tools/migrations_status.php">Migrations-Status</a>
    </div>
  </div>

  <?php if ($msg_ok): ?><div class="flash ok"><?= e($msg_ok) ?></div><?php endif; ?>
  <?php if ($msg_err): ?><div class="flash err"><?= e($msg_err) ?></div><?php endif; ?>

  <div class="card">
    <h3>Ziel-Einstellungen</h3>
    <p>
      Charset: <code><?= e(TARGET_CHARSET) ?></code> · Collation: <code><?= e(TARGET_COLLATE) ?></code>
      <span class="badge">Scope: <?= e($scope) ?></span>
    </p>
    <div class="row">
      <a class="ppc-button" href="/backend/tools/db_collation.php?action=preview&scope=core">Preview (core)</a>
      <a class="ppc-button" href="/backend/tools/db_collation.php?action=preview&scope=all">Preview (all)</a>
      <a class="ppc-button-secondary" href="/backend/tools/db_collation.php?action=apply&scope=core&dbcoll_csrf=<?= e($token) ?>" onclick="return confirm('Wirklich anwenden (core)?');">Apply (core)</a>
      <a class="ppc-button-secondary" href="/backend/tools/db_collation.php?action=apply&scope=all&dbcoll_csrf=<?= e($token) ?>" onclick="return confirm('Wirklich anwenden (alle InnoDB-Tabellen)?');">Apply (all)</a>
    </div>
    <p><small>Hinweis: „Apply (all)“ konvertiert alle InnoDB-Tabellen des Schemas. Für den Anfang empfiehlt sich „core“.</small></p>
  </div>

  <div class="card">
    <h3>Aktueller Status</h3>
    <p>
      Datenbank: <strong><?= e(current_db_name($db)) ?></strong><br>
      Default-Charset: <code><?= e((string)($status['db']['default_charset'] ?? '—')) ?></code><br>
      Default-Collation: <code><?= e((string)($status['db']['default_collation'] ?? '—')) ?></code>
    </p>
    <table>
      <thead><tr><th>Tabelle</th><th>Table-Collation</th></tr></thead>
      <tbody>
      <?php if (!$tables): ?>
        <tr><td colspan="2"><em>Keine Tabellen im gewählten Scope gefunden.</em></td></tr>
      <?php else: foreach ($tables as $t):
            $tc = (string)($status['tables'][$t]['table_collation'] ?? '—'); ?>
        <tr>
          <td><code><?= e($t) ?></code></td>
          <td><?= e($tc) ?><?= ($tc!==TARGET_COLLATE ? ' <span class="badge">abweichend</span>' : ' <span class="badge">OK</span>') ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h3>Geplante Statements (Preview)</h3>
    <table>
      <thead><tr><th>#</th><th>SQL</th></tr></thead>
      <tbody>
      <?php foreach ($plan as $i=>$sql): ?>
        <tr><td><?= (int)($i+1) ?></td><td><code><?= e($sql) ?></code></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p><small>Bei „Apply“ werden die obigen Anweisungen in dieser Reihenfolge ausgeführt. Fehler werden protokolliert: <code><?= e(rtrim(PPC_STORAGE,'/').'/logs/db_collation.log') ?></code></small></p>
  </div>

  <?php if (is_array($results)): ?>
  <div class="card">
    <h3>Ausführungs-Ergebnis</h3>
    <table>
      <thead><tr><th>Status</th><th>SQL</th><th>Fehler</th></tr></thead>
      <tbody>
      <?php foreach ($results as $r): ?>
        <tr>
          <td><?= $r['ok'] ? 'OK' : 'FEHLER' ?></td>
          <td><code><?= e($r['sql']) ?></code></td>
          <td><?= e((string)($r['err'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
