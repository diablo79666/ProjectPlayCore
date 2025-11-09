<?php
return function (PDO $db): void {
    $colExists = function (string $table, string $col) use ($db): bool {
        $st = $db->prepare("SELECT 1 FROM information_schema.COLUMNS
                             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c LIMIT 1");
        $st->execute([':t'=>$table, ':c'=>$col]);
        return (bool)$st->fetchColumn();
    };
    $indexExists = function (string $table, string $idx) use ($db): bool {
        $st = $db->prepare("SELECT 1 FROM information_schema.STATISTICS
                             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND INDEX_NAME=:i LIMIT 1");
        $st->execute([':t'=>$table, ':i'=>$idx]);
        return (bool)$st->fetchColumn();
    };

    $db->exec("CREATE TABLE IF NOT EXISTS users (
        username VARCHAR(190) PRIMARY KEY,
        password_hash TEXT NOT NULL,
        email VARCHAR(190) NULL,
        role VARCHAR(64) NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (!$colExists('users','dob'))         $db->exec("ALTER TABLE users ADD COLUMN dob DATE NULL AFTER role");
    if (!$colExists('users','kyc_status'))  $db->exec("ALTER TABLE users ADD COLUMN kyc_status VARCHAR(32) NOT NULL DEFAULT 'pending' AFTER dob");
    if (!$colExists('users','kyc_provider'))$db->exec("ALTER TABLE users ADD COLUMN kyc_provider VARCHAR(64) NULL AFTER kyc_status");
    if (!$colExists('users','kyc_ref'))     $db->exec("ALTER TABLE users ADD COLUMN kyc_ref VARCHAR(190) NULL AFTER kyc_provider");
    if (!$colExists('users','country'))     $db->exec("ALTER TABLE users ADD COLUMN country VARCHAR(2) NULL AFTER kyc_ref");
    if (!$colExists('users','doc_type'))    $db->exec("ALTER TABLE users ADD COLUMN doc_type VARCHAR(32) NULL AFTER country");

    if (!$indexExists('users','idx_users_kyc_status'))   $db->exec("CREATE INDEX idx_users_kyc_status ON users (kyc_status)");
    if (!$indexExists('users','idx_users_provider_ref')) $db->exec("CREATE INDEX idx_users_provider_ref ON users (kyc_provider,kyc_ref)");
};
