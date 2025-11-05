<?php
// ============================================================================
// Modul: person – Controller
// Zweck: Verwaltung und Anzeige des privaten Personenprofils
// Container-kompatibel: Unterstützt Manifest-Aktivierung & Health-System
// ============================================================================

declare(strict_types=1);

require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/utils.php';
require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../../../config.php';

// ------------------- Container-Metadaten -------------------
define('PPC_MODULE_NAME', 'person');
define('PPC_MODULE_VERSION', '1.1.0');
header('X-PPC-Module: person');
header('X-PPC-Container: active');

// ------------------- Sicherheitsprüfungen -------------------
ppc_security_headers();
ppc_require_login();

$db   = ppc_db();
$user = ppc_current_user() ?? '';

// ------------------- Hilfsfunktionen -------------------
function person_profile_load(PDO $db, string $user): array {
    try {
        $st = $db->prepare("SELECT * FROM person_profile WHERE username=:u LIMIT 1");
        $st->execute([':u'=>$user]);
        $data = $st->fetch(PDO::FETCH_ASSOC);
        if (!$data) {
            $data = ['username'=>$user,'firstname'=>'','lastname'=>'','dob'=>'','address'=>'','country'=>'','kyc_locked'=>0];
        }
        return $data;
    } catch (Throwable $t) {
        return ['error'=>$t->getMessage()];
    }
}

function person_profile_save(PDO $db, array $data): bool {
    try {
        $st = $db->prepare("
            INSERT INTO person_profile (username, firstname, lastname, dob, address, country, kyc_locked)
            VALUES (:username, :firstname, :lastname, :dob, :address, :country, :kyc_locked)
            ON DUPLICATE KEY UPDATE
                firstname=VALUES(firstname),
                lastname=VALUES(lastname),
                dob=VALUES(dob),
                address=VALUES(address),
                country=VALUES(country),
                kyc_locked=VALUES(kyc_locked)
        ");
        return $st->execute($data);
    } catch (Throwable $t) {
        return false;
    }
}

// ------------------- Aktionen -------------------
$action = strtolower((string)($_GET['action'] ?? $_POST['action'] ?? 'index'));

switch ($action) {
    case 'save':
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            exit('Invalid method');
        }

        $data = [
            'username'   => $user,
            'firstname'  => trim((string)($_POST['firstname'] ?? '')),
            'lastname'   => trim((string)($_POST['lastname'] ?? '')),
            'dob'        => trim((string)($_POST['dob'] ?? '')),
            'address'    => trim((string)($_POST['address'] ?? '')),
            'country'    => trim((string)($_POST['country'] ?? '')),
            'kyc_locked' => isset($_POST['kyc_locked']) ? 1 : 0,
        ];

        if (person_profile_save($db, $data)) {
            header('Location: /backend/modules/person/controller.php?action=index&saved=1');
            exit;
        } else {
            echo "❌ Fehler beim Speichern des Profils.";
            exit;
        }
        break;

    case 'index':
    default:
        $profile = person_profile_load($db, $user);
        $saved = isset($_GET['saved']);
        ?>
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="utf-8">
            <title>Persönliches Profil</title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <link rel="stylesheet" href="/assets/style.css">
            <style>
                .wrap{max-width:720px;margin:30px auto}
                .card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 2px 10px rgba(0,0,0,.06)}
                .input{width:100%;padding:10px;border:1px solid #d8dde6;border-radius:8px;margin-bottom:8px}
                .button{padding:10px 14px;background:#0b4d91;color:#fff;border-radius:8px;text-decoration:none;border:0;cursor:pointer}
                .gray{background:#6b7280}
                .note{color:#0b8a4d;margin:8px 0}
                label{display:block;margin-bottom:4px;font-weight:600}
            </style>
        </head>
        <body>
        <div class="wrap">
          <div class="card">
            <h2>Persönliches Profil</h2>
            <?php if ($saved): ?><p class="note">✅ Profil gespeichert.</p><?php endif; ?>
            <form method="post" action="/backend/modules/person/controller.php?action=save">
              <label>Vorname</label>
              <input class="input" type="text" name="firstname" value="<?php echo htmlspecialchars($profile['firstname'] ?? '', ENT_QUOTES); ?>">
              <label>Nachname</label>
              <input class="input" type="text" name="lastname" value="<?php echo htmlspecialchars($profile['lastname'] ?? '', ENT_QUOTES); ?>">
              <label>Geburtsdatum</label>
              <input class="input" type="date" name="dob" value="<?php echo htmlspecialchars($profile['dob'] ?? '', ENT_QUOTES); ?>">
              <label>Adresse</label>
              <input class="input" type="text" name="address" value="<?php echo htmlspecialchars($profile['address'] ?? '', ENT_QUOTES); ?>">
              <label>Land</label>
              <input class="input" type="text" name="country" value="<?php echo htmlspecialchars($profile['country'] ?? '', ENT_QUOTES); ?>">
              <label><input type="checkbox" name="kyc_locked" value="1" <?php echo !empty($profile['kyc_locked']) ? 'checked' : ''; ?>> Gesperrt durch KYC</label>
              <div style="display:flex;gap:10px;margin-top:12px">
                <button class="button" type="submit">Speichern</button>
                <a class="button gray" href="/backend/">Zurück</a>
              </div>
            </form>
          </div>
        </div>
        </body>
        </html>
        <?php
        exit;
}
