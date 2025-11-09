<?php
/**
 * profiles – Migration 001_init
 * Erstellt parents, children (+child_email), child_policies, child_audit (idempotent)
 */
return function(PDO $db): void {
    // parents
    $db->exec("
      CREATE TABLE IF NOT EXISTS parents (
        parent_id INT PRIMARY KEY AUTO_INCREMENT,
        username  VARCHAR(190) NOT NULL UNIQUE,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // children
    $db->exec("
      CREATE TABLE IF NOT EXISTS children (
        child_id INT PRIMARY KEY AUTO_INCREMENT,
        parent_id INT NOT NULL,
        child_username VARCHAR(190) NOT NULL UNIQUE,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_children_parent FOREIGN KEY (parent_id)
          REFERENCES parents(parent_id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Spalte child_email falls fehlend
    $st = $db->prepare("
      SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='children' AND COLUMN_NAME='child_email'
    ");
    $st->execute();
    if ((int)$st->fetchColumn() === 0) {
        $db->exec("ALTER TABLE children ADD COLUMN child_email VARCHAR(190) NULL AFTER child_username");
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_children_email ON children (child_email)");
    }

    // child_policies
    $db->exec("
      CREATE TABLE IF NOT EXISTS child_policies (
        child_id INT NOT NULL,
        can_email  TINYINT(1) NOT NULL DEFAULT 0,
        can_site   TINYINT(1) NOT NULL DEFAULT 0,
        can_chat   TINYINT(1) NOT NULL DEFAULT 1,
        daily_minutes INT NOT NULL DEFAULT 0,
        PRIMARY KEY (child_id),
        CONSTRAINT fk_cp_child FOREIGN KEY (child_id)
          REFERENCES children(child_id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // child_audit
    $db->exec("
      CREATE TABLE IF NOT EXISTS child_audit (
        id INT PRIMARY KEY AUTO_INCREMENT,
        child_id  INT NOT NULL,
        parent_id INT NOT NULL,
        action    VARCHAR(64) NOT NULL,
        details   TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX ix_child (child_id),
        CONSTRAINT fk_ca_child FOREIGN KEY (child_id) REFERENCES children(child_id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // „best effort“: case-insensitive Unique-Indices (kann je nach MySQL-Version ignoriert werden)
    try { $db->exec("CREATE UNIQUE INDEX uq_users_email_ci    ON users ((LOWER(email)))"); } catch (Throwable $t) {}
    try { $db->exec("CREATE UNIQUE INDEX uq_users_username_ci ON users ((LOWER(username)))"); } catch (Throwable $t) {}
};
