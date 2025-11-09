<?php
/**
 * Migration: ppp_pages (Seiten-Registry) + Seed für /, /login, /profil
 */
return function (PDO $db): void {
    // Tabelle
    $db->exec("
        CREATE TABLE IF NOT EXISTS ppp_pages (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(190) NOT NULL UNIQUE,
            title VARCHAR(190) NOT NULL,
            status ENUM('draft','published') NOT NULL DEFAULT 'published',
            content MEDIUMTEXT NULL,
            template VARCHAR(190) NULL,
            override_path VARCHAR(255) NULL,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Helper: existiert slug?
    $exists = $db->prepare("SELECT 1 FROM ppp_pages WHERE slug=:s LIMIT 1");

    // Seed / (Home)
    $exists->execute([':s' => '']);
    if (!$exists->fetchColumn()) {
        $db->prepare("INSERT INTO ppp_pages(slug,title,status,content,override_path)
                      VALUES('','Startseite','published',
                      'Willkommen bei ProjectPlayCore. Diese Seite kannst du im Admin ändern.',
                      '/frontend/overrides/system/pages/home.php')")
           ->execute();
    }

    // Seed /login
    $exists->execute([':s' => 'login']);
    if (!$exists->fetchColumn()) {
        $db->prepare("INSERT INTO ppp_pages(slug,title,status,content,override_path)
                      VALUES('login','Login','published',
                      'Login-Seite (Override-Datei vorhanden).','/frontend/overrides/system/pages/login.php')")
           ->execute();
    }

    // Seed /profil (Wrapper – nutzt interne Person/Profil-Logik)
    $exists->execute([':s' => 'profil']);
    if (!$exists->fetchColumn()) {
        $db->prepare("INSERT INTO ppp_pages(slug,title,status,content,override_path)
                      VALUES('profil','Profil','published',
                      'Profil-Seite (zieht Person-Controller).',
                      '/frontend/overrides/person/profile.php')")
           ->execute();
    }
};
