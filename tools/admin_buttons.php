<?php
// ============================================================================
// ProjectPlayCore – Automatischer Admin-Button-Generator
// Pfad: /backend/tools/admin_buttons.php
// Beschreibung:
//  Scannt /backend/tools/ und /backend/modules/ nach Tool- bzw. Modul-Dateien
//  und erzeugt dynamisch alle Buttons im Admin-Dashboard.
// ============================================================================

declare(strict_types=1);

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../modules/loader.php';

ppc_security_headers();
ppc_require_login();

use PDO;

// ---------------------------------------------------------------------------
// Datenbankverbindung
// ---------------------------------------------------------------------------
$db = ppc_db();
$buttons = [];

// ---------------------------------------------------------------------------
// 1. /backend/tools/ nach @admin-button-Kommentaren scannen
// ---------------------------------------------------------------------------
$toolsDir = realpath(__DIR__);
if ($toolsDir && is_dir($toolsDir)) {
    foreach (glob($toolsDir . '/*.php') as $file) {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '@admin-button:') !== false) {
                // Beispiel: @admin-button: Titel | /backend/tools/datei.php | Gruppe | 10
                if (preg_match('/@admin-button:\s*(.*?)\s*\|\s*(.*?)\s*\|\s*(.*?)\s*\|\s*(\d+)/', $line, $m)) {
                    $buttons[] = [
                        'title'      => trim($m[1]),
                        'href'       => trim($m[2]),
                        'btn_group'  => trim($m[3]),
                        'sort_order' => (int)$m[4],
                        'enabled'    => 1,
                    ];
                }
            }
        }
    }
}

// ---------------------------------------------------------------------------
// 2. /backend/modules/ nach module.json scannen
// ---------------------------------------------------------------------------
$modulesDir = realpath(__DIR__ . '/../modules');
if ($modulesDir && is_dir($modulesDir)) {
    foreach (glob($modulesDir . '/*/module.json') as $manifestPath) {
        $modDir  = dirname($manifestPath);
        $modName = basename($modDir);
        $json    = @file_get_contents($manifestPath);
        $data    = json_decode($json ?: 'null', true);
        if (!is_array($data)) continue;

        $title = $data['service'] ?? ucfirst($modName);
        $href  = "/backend/modules/{$modName}/admin.php";
        $group = 'Module';
        $order = (int)($data['admin'][0]['order'] ?? 100);

        $buttons[] = [
            'title'      => $title,
            'href'       => $href,
            'btn_group'  => $group,
            'sort_order' => $order,
            'enabled'    => 1,
        ];
    }
}

// ---------------------------------------------------------------------------
// 3. In Datenbank schreiben (admin_buttons)
// ---------------------------------------------------------------------------
try {
    $db->exec("CREATE TABLE IF NOT EXISTS admin_buttons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        href VARCHAR(255) NOT NULL,
        btn_group VARCHAR(100) DEFAULT 'System',
        sort_order INT DEFAULT 0,
        enabled TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Alte Einträge löschen
    $db->exec("DELETE FROM admin_buttons");

    // Neue Buttons einfügen
    $stmt = $db->prepare("INSERT INTO admin_buttons (title, href, btn_group, sort_order, enabled) VALUES (:t, :h, :g, :o, :e)");
    foreach ($buttons as $btn) {
        $stmt->execute([
            ':t' => $btn['title'],
            ':h' => $btn['href'],
            ':g' => $btn['btn_group'],
            ':o' => $btn['sort_order'],
            ':e' => $btn['enabled']
        ]);
    }

} catch (Throwable $t) {
    die("❌ Fehler beim Schreiben der Buttons: " . htmlspecialchars($t->getMessage()));
}

// ---------------------------------------------------------------------------
// 4. Ausgabe
// ---------------------------------------------------------------------------
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Admin Buttons Übersicht</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="ppc-container" style="max-width:900px;margin:2rem auto">
<div class="ppc-card">
  <h1>Admin Buttons Übersicht</h1>
  <p>Es wurden <?= count($buttons) ?> Buttons registriert.</p>
  <table style="width:100%;border-collapse:collapse;margin-top:1rem;">
    <thead><tr><th>Titel</th><th>Pfad</th><th>Gruppe</th><th>Sortierung</th></tr></thead>
    <tbody>
      <?php foreach ($buttons as $b): ?>
        <tr>
          <td><?= htmlspecialchars($b['title']) ?></td>
          <td><?= htmlspecialchars($b['href']) ?></td>
          <td><?= htmlspecialchars($b['btn_group']) ?></td>
          <td><?= htmlspecialchars((string)$b['sort_order']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p><a href="/backend/" class="ppc-button">Zurück zum Dashboard</a></p>
</div>
</body>
</html>
