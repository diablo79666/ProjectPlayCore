<?php
/**
 * person/migrations/001_person_profile.php
 * (Optional – falls du Migrationen zentral triggerst. Der Loader legt die Tabelle bereits an.)
 */
return function (PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS person_profile (
            username    VARCHAR(190) PRIMARY KEY,
            realname    VARCHAR(190) NULL,
            dob         DATE NULL,
            street      VARCHAR(190) NULL,
            zip         VARCHAR(32)  NULL,
            city        VARCHAR(190) NULL,
            country     VARCHAR(2)   NULL,
            created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
};
