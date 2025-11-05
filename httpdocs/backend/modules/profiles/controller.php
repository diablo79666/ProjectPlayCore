<?php
// ============================================================================
// Modul: profiles – Controller (Eltern-/Kinderprofile, Policies, Audit)
// Dezentralisiert & Container-kompatibel (Health-/Discovery-Header)
// Aktionen (GET/POST action=...):
//   index         → Übersicht Eltern + Kinder
//   child_add     → Formular Kind anlegen
//   child_save    → POST Insert/Update Kind
//   child_del     → Kind löschen
//   policy_set    → einfache Policy setzen (z. B. "playtime_limit")
// ============================================================================

declare(strict_types=1);

require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/utils.php';
require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../../../config.php';

ppc_security_headers();
ppc_require_login();

// ------------------- Container-Metadaten -------------------
define('PPC_MODULE_NAME', 'profiles');
define('PPC_MODULE_VERSION', '1.1.0');
header('X-PPC-Module: profiles');
header('X-PPC-Container: active');

$db   = ppc_db();
$user = (string)(ppc_current_user() ?? '');

// ------------------- Setup: Tabellen falls nötig -------------------
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS parents (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(191) NOT NULL UNIQUE,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS children (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            parent_username VARCHAR(191) NOT NULL,
            child_name VARCHAR(191) NOT NULL,
            dob DATE NULL,
            country VARCHAR(64) NULL,
            notes TEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_parent (parent_username),
            CONSTRAINT fk_parent_username FOREIGN KEY (parent_username) REFERENCES parents(username) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS child_policies (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            child_id INT UNSIGNED NOT NULL,
            policy_key VARCHAR(64) NOT NULL,
            policy_value VARCHAR(191) NOT NULL,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_child_policy (child_id, policy_key),
            CONSTRAINT fk_child_policy FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS child_audit (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            child_id INT UNSIGNED NOT NULL,
            event VARCHAR(64) NOT NULL,
            meta TEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_child (child_id),
            CONSTRAINT fk_child_audit FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Throwable $t) {
    http_response_code(500);
    exit('DB-Setup-Fehler: ' . htmlspecialchars($t->getMessage(), ENT_QUOTES));
}

// ------------------- Helper -------------------
function profiles_parent_ensure(PDO $db, string $username): void {
    $st = $db->prepare("INSERT IGNORE INTO parents (username) VALUES (:u)");
    $st->execute([':u'=>$username]);
}
function profiles_children(PDO $db, string $username): array {
    $st = $db->prepare("SELECT * FROM children WHERE parent_username=:u ORDER BY created_at DESC, id DESC");
    $st->execute([':u'=>$username]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
function profiles_child_get(PDO $db, int $id, string $username): ?array {
    $st = $db->prepare("SELECT * FROM children WHERE id=:id AND parent_username=:u LIMIT 1");
    $st->execute([':id'=>$id, ':u'=>$username]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}
function profiles_child_save(PDO $db, string $username, ?int $id, string $name, ?string $dob, ?string $country, ?string $notes): bool {
    if ($id) {
        $st = $db->prepare("
            UPDATE children
               SET child_name=:n, dob=:d, country=:c, notes=:notes
             WHERE id=:id AND parent_username=:u
        ");
        return $st->execute([':n'=>$name, ':d'=>$dob, ':c'=>$country, ':notes'=>$notes, ':id'=>$id, ':u'=>$username]);
    } else {
        $st = $db->prepare("
            INSERT INTO children (parent_username, child_name, dob, country, notes)
            VALUES (:u, :n, :d, :c, :notes)
        ");
        return $st->execute([':u'=>$username, ':n'=>$name, ':d'=>$dob, ':c'=>$country, ':notes'=>$notes]);
    }
}
function profiles_child_del(PDO $db, string $username, int $id): bool {
    $st = $db->prepare("DELETE FROM children WHERE id=:id AND parent_username=:u");
    return $st->execute([':id'=>$id, ':u'=>$username]);
}
function profiles_policy_set(PDO $db, int $childId, string $key, string $val): bool {
    $st = $db->prepare("
        INSERT INTO child_policies (child_id, policy_key, policy_value)
        VALUES (:cid, :k, :v)
        ON DUPLICATE KEY UPDATE policy_value=VALUES(policy_value)
    ");
    $ok = $st->execute([':cid'=>$childId, ':k'=>$key, ':v'=>$val]);
    if ($ok) {
        $log = $db->prepare("INSERT INTO child_audit (child_id, event, meta) VALUES (:cid, 'policy_set', :meta)");
        $log->execute([':cid'=>$childId, ':meta'=>json_encode(['key'=>$key,'value'=>$val], JSON_UNESCAPED_UNICODE)]);
    }
    return $ok;
}

// ------------------- Routing -------------------
$action = strtolower((string)($_GET['action'] ?? $_POST['action'] ?? 'index'));
profiles_parent_ensure($db, $user);

switch ($action) {
    case 'child_add':
        $childId = isset($_GET['id']) ? (int)$_GET['id'] : null;
        $child   = $childId ? profiles_child_get($db, $childId, $user) : null;
        profiles_render_form($child);
        break;

    case 'child_save':
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            exit('Invalid method');
        }
        $id      = isset($_POST['id']) ? (int)$_POST['id'] : null;
        $name    = trim((string)($_POST['child_name'] ?? ''));
        $dob     = trim((string)($_POST['dob'] ?? '')) ?: null;
        $country = trim((string)($_POST['country'] ?? '')) ?: null;
        $notes   = trim((string)($_POST['notes'] ?? '')) ?: null;

        if ($name === '') {
            http_response_code(400);
            exit('Name erforderlich.');
        }
        $ok = profiles_child_save($db, $user, $id, $name, $dob, $country, $notes);
        header('Location: /backend/modules/profiles/controller.php?action=index&saved=' . ($ok ? '1' : '0'));
        exit;

    case 'child_del':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id > 0) profiles_child_del($db, $user, $id);
        header('Location: /backend/modules/profiles/controller.php?action=index&deleted=1');
        exit;

    case 'policy_set':
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            exit('Invalid method');
        }
        $childId = (int)($_POST['child_id'] ?? 0);
        $key     = trim((string)($_POST['key'] ?? ''));
        $val     = trim((string)($_POST['value'] ?? ''));
        if ($childId > 0 && $key !== '') {
            profiles_policy_set($db, $childId, $key, $val);
        }
        header('Location: /backend/modules/profiles/controller.php?action=index&policy=1');
        exit;

    case 'index':
    default:
        $list = profiles_children($db, $user);
        profiles_render_index($list);
        break;
}

// ------------------- Render-Funktionen -------------------
function profiles_render_index(array $children): void {
    $css = is_file(dirname(__DIR__, 3) . '/assets/style.css') ? '/assets/style.css' : null;
    $saved   = isset($_GET['saved']);
    $deleted = isset($_GET['deleted']);
    $policy  = isset($_GET['policy']);
    ?>
    <!doctype html>
    <html lang="de">
    <head>
      <meta charset="utf-8">
      <title>Profile – Übersicht</title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <?php if ($css): ?><link rel="stylesheet" href="<?php echo htmlspecialchars($css, ENT_QUOTES); ?>"><?php endif; ?>
      <style>
        .wrap{max-width:960px;margin:30px auto}
        .card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:14px}
        .button{padding:10px 14px;background:#0b4d91;color:#fff;border-radius:8px;text-decoration:none;display:inline-block}
        .danger{background:#991b1b}.small{font-size:.9rem;color:#667}
        table{width:100%;border-collapse:collapse}
        th,td{padding:8px;border-bottom:1px solid #e5e7eb;text-align:left}
        .actions{display:flex;gap:8px}
        form.inline{display:inline}
        input[type=text],input[type=date],textarea{width:100%;padding:10px;border:1px solid #d8dde6;border-radius:8px}
      </style>
    </head>
    <body>
    <div class="wrap">
      <div class="card">
        <h2 style="margin:0 0 10px">Profile – Übersicht</h2>
        <?php if ($saved): ?><p class="small">✅ Kind gespeichert.</p><?php endif; ?>
        <?php if ($deleted): ?><p class="small">🗑️ Kind gelöscht.</p><?php endif; ?>
        <?php if ($policy): ?><p class="small">🔧 Policy aktualisiert.</p><?php endif; ?>
        <p><a class="button" href="/backend/modules/profiles/controller.php?action=child_add">Kind hinzufügen</a></p>
        <?php if (!$children): ?>
          <p class="small">Noch keine Einträge vorhanden.</p>
        <?php else: ?>
          <table>
            <thead><tr><th>Name</th><th>Geburtsdatum</th><th>Land</th><th>Notizen</th><th>Aktionen</th></tr></thead>
            <tbody>
              <?php foreach ($children as $c): ?>
              <tr>
                <td><?php echo htmlspecialchars($c['child_name'], ENT_QUOTES); ?></td>
                <td><?php echo htmlspecialchars((string)$c['dob'], ENT_QUOTES); ?></td>
                <td><?php echo htmlspecialchars((string)$c['country'], ENT_QUOTES); ?></td>
                <td><?php echo htmlspecialchars((string)$c['notes'], ENT_QUOTES); ?></td>
                <td class="actions">
                  <a class="button" href="/backend/modules/profiles/controller.php?action=child_add&id=<?php echo (int)$c['id']; ?>">Bearbeiten</a>
                  <a class="button danger" href="/backend/modules/profiles/controller.php?action=child_del&id=<?php echo (int)$c['id']; ?>" onclick="return confirm('Wirklich löschen?');">Löschen</a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <div class="card" style="margin-top:14px">
            <h3 style="margin-top:0">Schnelle Policy setzen</h3>
            <form method="post" action="/backend/modules/profiles/controller.php?action=policy_set">
              <div style="display:flex;gap:8px;flex-wrap:wrap">
                <input type="hidden" name="child_id" id="child_id" value="<?php echo (int)($children[0]['id'] ?? 0); ?>">
                <input type="text" name="key" placeholder="policy_key (z.B. playtime_limit)">
                <input type="text" name="value" placeholder="policy_value (z.B. 120)">
                <button class="button" type="submit">Setzen</button>
              </div>
              <p class="small">Hinweis: Für erweiterte Einstellungen bitte in den Container „profiles“ Detailfunktionen ausbauen.</p>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </div>
    </body>
    </html>
    <?php
}

function profiles_render_form(?array $child): void {
    $css = is_file(dirname(__DIR__, 3) . '/assets/style.css') ? '/assets/style.css' : null;
    $id  = $child['id'] ?? null;
    $name= $child['child_name'] ?? '';
    $dob = $child['dob'] ?? '';
    $country = $child['country'] ?? '';
    $notes   = $child['notes'] ?? '';
    ?>
    <!doctype html>
    <html lang="de">
    <head>
      <meta charset="utf-8">
      <title><?php echo $id ? 'Kind bearbeiten' : 'Kind hinzufügen'; ?></title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <?php if ($css): ?><link rel="stylesheet" href="<?php echo htmlspecialchars($css, ENT_QUOTES); ?>"><?php endif; ?>
      <style>
        .wrap{max-width:720px;margin:30px auto}
        .card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 2px 10px rgba(0,0,0,.06)}
        label{display:block;margin-bottom:4px;font-weight:600}
        .input,textarea{width:100%;padding:10px;border:1px solid #d8dde6;border-radius:8px;margin-bottom:8px}
        .button{padding:10px 14px;background:#0b4d91;color:#fff;border-radius:8px;text-decoration:none;border:0;cursor:pointer}
        .gray{background:#6b7280}
      </style>
    </head>
    <body>
    <div class="wrap">
      <div class="card">
        <h2 style="margin:0 0 10px"><?php echo $id ? 'Kind bearbeiten' : 'Kind hinzufügen'; ?></h2>
        <form method="post" action="/backend/modules/profiles/controller.php?action=child_save">
          <?php if ($id): ?><input type="hidden" name="id" value="<?php echo (int)$id; ?>"><?php endif; ?>
          <label>Name</label>
          <input class="input" type="text" name="child_name" value="<?php echo htmlspecialchars($name, ENT_QUOTES); ?>" required>
          <label>Geburtsdatum</label>
          <input class="input" type="date" name="dob" value="<?php echo htmlspecialchars((string)$dob, ENT_QUOTES); ?>">
          <label>Land</label>
          <input class="input" type="text" name="country" value="<?php echo htmlspecialchars((string)$country, ENT_QUOTES); ?>">
          <label>Notizen</label>
          <textarea class="input" name="notes" rows="4"><?php echo htmlspecialchars((string)$notes, ENT_QUOTES); ?></textarea>
          <div style="display:flex;gap:10px;margin-top:12px">
            <button class="button" type="submit">Speichern</button>
            <a class="button gray" href="/backend/modules/profiles/controller.php?action=index">Abbrechen</a>
          </div>
        </form>
      </div>
    </div>
    </body>
    </html>
    <?php
}
