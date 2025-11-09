<?php
// ============================================================================
// Modul: identity – Settings-Funktionen (DB-Basis)
// ============================================================================

declare(strict_types=1);
require_once __DIR__ . '/../../database/db.php';

function identity_setting_get(string $key, ?string $default=null): ?string {
    $db = ppc_db();
    try {
        $st = $db->prepare("SELECT item_value FROM ppc_settings WHERE group_key='identity' AND item_key=:k LIMIT 1");
        $st->execute([':k'=>$key]);
        $v = $st->fetchColumn();
        return ($v === false || $v === null) ? $default : (string)$v;
    } catch (Throwable $t) {
        return $default;
    }
}

function identity_setting_set(string $key, string $value): bool {
    $db = ppc_db();
    try {
        $st = $db->prepare("
            INSERT INTO ppc_settings (group_key, item_key, item_value)
            VALUES ('identity', :k, :v)
            ON DUPLICATE KEY UPDATE item_value=VALUES(item_value)
        ");
        $st->execute([':k'=>$key, ':v'=>$value]);
        return true;
    } catch (Throwable $t) {
        return false;
    }
}
