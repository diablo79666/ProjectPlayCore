<?php
/**
 * PPC – Zentraler Migrations-Runner (v2, ohne explizite Transaktionen)
 * - Läuft alle migrations/*.php der aktivierten Module
 * - Merkt Stand in ppc_module_versions (PRIMARY KEY (module,name))
 * - Kein begin/commit: MySQL-DDL macht implizite Commits → stabil ohne „There is no active transaction“
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/loader.php';

@mkdir(sys_get_temp_dir().'/ppc_storage/logs', 0775, true);
function ppc_mig_log(string $msg): void {
    @file_put_contents(sys_get_temp_dir().'/ppc_storage/logs/migrations.log', '['.date('c')."] ".$msg."\n", FILE_APPEND);
}

function ppc_mig_db(): PDO { return ppc_db(); }

function ppc_mig_enabled_modules(PDO $db): array {
    try {
        $st = $db->query("SELECT name FROM modules WHERE enabled=1 ORDER BY name ASC");
        return $st ? array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN,0) ?: []) : [];
    } catch (Throwable $t) {
        ppc_mig_log("modules list error: ".$t->getMessage());
        return [];
    }
}

/** Merktabelle sicherstellen (im Einklang mit fix_migrations_table.php) */
function ppc_mig_ensure_table(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS ppc_module_versions (
        module     VARCHAR(64)  NOT NULL,
        name       VARCHAR(190) NOT NULL,
        applied_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (module, name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** true => Migration bereits ausgeführt */
function ppc_mig_is_applied(PDO $db, string $module, string $name): bool {
    $st = $db->prepare("SELECT 1 FROM ppc_module_versions WHERE module=:m AND name=:n LIMIT 1");
    $st->execute([':m'=>$module, ':n'=>$name]);
    return (bool)$st->fetchColumn();
}

/** markiert Migration als ausgeführt (idempotent) */
function ppc_mig_mark_applied(PDO $db, string $module, string $name): void {
    $st = $db->prepare("INSERT IGNORE INTO ppc_module_versions(module,name) VALUES(:m,:n)");
    $st->execute([':m'=>$module, ':n'=>$name]);
}

/** Läuft eine einzelne Migration (Closure `function(PDO $db): void`) */
function ppc_mig_run_one(PDO $db, string $module, string $name, callable $fn): void {
    if (ppc_mig_is_applied($db,$module,$name)) {
        ppc_mig_log("skip {$module}/{$name}");
        return;
    }
    ppc_mig_log("run  {$module}/{$name}");
    try {
        $fn($db); // Migration führt ihre DDL/DML selbst aus (DDL => impliziter Commit bei MySQL)
        ppc_mig_mark_applied($db,$module,$name);
    } catch (Throwable $t) {
        ppc_mig_log("ERR  {$module}/{$name}: ".$t->getMessage());
        throw $t;
    }
}

/** Hauptfunktion: alle Migrationen über aktivierte Module */
function ppc_migrations_run_for_enabled(): void {
    $db = ppc_mig_db();
    ppc_mig_ensure_table($db);

    $root   = __DIR__; // /backend/modules
    $mods   = ppc_mig_enabled_modules($db);
    $errors = [];
    $skips  = [];

    foreach ($mods as $mod) {
        $mDir = $root.'/'.$mod.'/migrations';
        if (!is_dir($mDir)) continue;

        $files = glob($mDir.'/*.php') ?: [];
        natsort($files);

        foreach ($files as $file) {
            $name = basename($file);
            if (ppc_mig_is_applied($db,$mod,$name)) { $skips[] = "{$mod}/{$name}"; continue; }

            // Migration muss eine Callable zurückgeben: return function(PDO $db): void { ... }
            $closure = (static function(string $path) {
                /** @var callable $c */
                $c = require $path;
                return $c;
            })($file);

            try {
                if (!is_callable($closure)) throw new RuntimeException("Migration liefert keine Callable: {$mod}/{$name}");
                ppc_mig_run_one($db,$mod,$name,$closure);
            } catch (Throwable $t) {
                $errors[] = "{$mod}/{$name}: ".$t->getMessage();
            }
        }
    }

    $out = "skipped: ".($skips ? implode(', ', $skips) : '—')."\n\n";
    $out.= "errors: ".($errors ? implode(' | ', $errors) : '—')."\n";
    @file_put_contents(sys_get_temp_dir().'/ppc_storage/logs/migrations_result.txt', $out);

    header('Content-Type: text/plain; charset=utf-8');
    echo $out;
}
