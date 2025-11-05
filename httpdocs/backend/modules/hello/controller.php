<?php
// ============================================================================
// Modul: hello – Controller (Ping + Settings über JSON-Datei)
// Dezentralisiert – mit Container-Metadaten und Health-Headern
// Aufrufbeispiele:
//   /backend/modules/hello/controller.php?action=ping
//   /backend/modules/hello/controller.php?action=settings
// ============================================================================

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/backend/core/session.php';
require_once dirname(__DIR__, 3) . '/backend/core/utils.php';
require_once dirname(__DIR__, 3) . '/backend/core/security.php';

// -----------------------------------------------------------
// Container-Metadaten (für Sync & Health-System)
// -----------------------------------------------------------
define('PPC_MODULE_NAME', 'hello');
define('PPC_MODULE_VERSION', '1.1.0');
header('X-PPC-Module: hello');
header('X-PPC-Container: active');

// Nur eingeloggte Nutzer
ppc_require_login();

$action = (string)($_GET['action'] ?? 'ping');
$css = is_file(dirname(__DIR__, 3) . '/assets/style.css') ? '/assets/style.css'
     : (is_file(dirname(__DIR__, 3) . '/style.css') ? '/style.css' : null);

// -----------------------------------------------------------
// Daten-Storage
// -----------------------------------------------------------
$storeDir  = dirname(__DIR__, 3) . '/storage/modules';
$storeFile = $storeDir . '/hello.json';
if (!is_dir($storeDir)) { @mkdir($storeDir, 0777, true); }

// -----------------------------------------------------------
// Hilfsfunktionen
// -----------------------------------------------------------
function hello_load(): array {
    global $storeFile;
    if (is_file($storeFile)) {
        $raw = @file_get_contents($storeFile);
        $d = $raw ? json_decode($raw, true) : null;
        if (is_array($d)) return $d;
    }
    return ['greeting' => 'Hallo Welt', 'enabled' => true];
}

function hello_save(array $data): void {
    global $storeFile;
    @file_put_contents($storeFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// -----------------------------------------------------------
// Aktionen
// -----------------------------------------------------------

// --- Ping-Check ---
if ($action === 'ping') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'      => true,
        'module'  => 'hello',
        'version' => PPC_MODULE_VERSION,
        'time'    => time()
    ]);
    exit;
}

// --- Einstellungen ---
if ($action === 'settings') {
    $notice = '';
    $data = hello_load();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        // einfache CSRF via Double-Submit
        $csrfName  = 'hello_csrf';
        $secure    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $cookieTok = $_COOKIE[$csrfName] ?? '';
        $sentTok   = (string)($_POST[$csrfName] ?? '');

        if ($cookieTok === '' || $sentTok === '' || !hash_equals((string)$cookieTok, (string)$sentTok)) {
            $notice = 'Sicherheitsprüfung fehlgeschlagen. Bitte erneut versuchen.';
        } else {
            $g  = trim((string)($_POST['greeting'] ?? ''));
            $en = isset($_POST['enabled']);
            if ($g === '') { $g = 'Hallo Welt'; }
            $data = ['greeting' => $g, 'enabled' => $en];
            hello_save($data);
            $notice = 'Einstellungen gespeichert.';
        }
    }

    // CSRF-Cookie setzen/erneuern
    $csrfName  = 'hello_csrf';
    $secure    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $csrfToken = $_COOKIE[$csrfName] ?? bin2hex(random_bytes(24));
    setcookie($csrfName, $csrfToken, [
        'expires'  => time() + 3600,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
      <meta charset="utf-8">
      <title>Hello – Einstellungen</title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <?php if ($css): ?><link rel="stylesheet" href="<?php echo htmlspecialchars($css, ENT_QUOTES); ?>"><?php endif; ?>
      <style>
        .wrap{max-width:720px;margin:30px auto}
        .card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 2px 10px rgba(0,0,0,.06)}
        .label{display:block;margin:8px 0 4px}
        .input{width:100%;padding:10px;border:1px solid #d8dde6;border-radius:8px}
        .switch{display:flex;align-items:center;gap:8px;margin:8px 0}
        .button{display:inline-block;padding:10px 14px;background:#0b4d91;color:#fff;border-radius:8px;text-decoration:none;border:0;cursor:pointer}
        .button.gray{background:#6b7280}
        .note{color:#0b8a4d;margin:8px 0}
      </style>
    </head>
    <body>
      <div class="wrap">
        <div class="card">
          <h2 style="margin:0 0 10px">Hello – Einstellungen</h2>
          <?php if ($notice): ?><p class="note"><?php echo htmlspecialchars($notice, ENT_QUOTES); ?></p><?php endif; ?>
          <form method="post" action="/backend/modules/hello/controller.php?action=settings">
            <input type="hidden" name="hello_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
            <label class="label">Begrüßung</label>
            <input class="input" name="greeting" value="<?php echo htmlspecialchars((string)$data['greeting'], ENT_QUOTES); ?>">
            <label class="switch"><input type="checkbox" name="enabled" <?php echo !empty($data['enabled']) ? 'checked' : ''; ?>> Aktiviert</label>
            <div style="display:flex;gap:10px;margin-top:12px">
              <button class="button" type="submit">Speichern</button>
              <a class="button gray" href="/backend/">Zurück zum Admin</a>
              <a class="button" href="/backend/modules/hello/controller.php?action=ping">Ping testen</a>
            </div>
          </form>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// --- Unbekannte Aktion ---
http_response_code(404);
echo 'Unknown action.';
