<?php
/**
 * Module: template
 * Controller-Endpunkte:
 *   - ?action=about
 *   - ?action=settings        (wenn Roles aktiv: Cap 'manage_modules' erforderlich)
 *   - ?action=settings_save   (POST; CSRF Double-Submit; Cap wenn vorhanden)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/utils.php';
require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../../../config.php';

ppc_security_headers();
ppc_require_login();

$moduleName   = basename(dirname(__FILE__));
$settingsFile = PPC_STORAGE . '/modules/' . $moduleName . '.json';
$csrfCookie   = 'modules_csrf';

// Settings-IO
function tpl_read_settings(string $file): array {
    if (!is_dir(dirname($file))) { @mkdir(dirname($file), 0775, true); }
    if (!file_exists($file)) return [];
    $json = @file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}
function tpl_write_settings(string $file, array $data): bool {
    if (!is_dir(dirname($file))) { @mkdir(dirname($file), 0775, true); }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return @file_put_contents($file, $json, LOCK_EX) !== false;
}

$action = strtolower((string)($_GET['action'] ?? 'about'));

switch ($action) {
    case 'about':
        $user = ppc_current_user();
        ?>
        <!doctype html><html lang="de"><head>
            <meta charset="utf-8">
            <title>Module: <?=$moduleName?> – About</title>
            <link rel="stylesheet" href="/assets/style.css">
        </head><body class="ppc-container">
            <h1>Module: <?=$moduleName?> – About</h1>
            <p>Angemeldet als: <strong><?=e($user ?? 'unbekannt')?></strong></p>
            <p>Dies ist das Starter-Template für neue Module. Kopiere den Ordner <code>template</code> und benenne ihn um.</p>
            <ul>
                <li><a href="/backend/modules/<?=$moduleName?>/controller.php?action=settings">Einstellungen</a> (erfordert <code>manage_modules</code>, falls Rollen-Modul aktiv)</li>
            </ul>
        </body></html>
        <?php
        break;

    case 'settings':
        // Cap nur prüfen, wenn Roles-Helfer vorhanden sind
        if (function_exists('ppc_require_cap')) {
            ppc_require_cap('manage_modules');
        }

        // CSRF vorbereiten
        $token = bin2hex(random_bytes(16));
        setcookie($csrfCookie, $token, [
            'expires'  => time() + 3600,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        $settings = tpl_read_settings($settingsFile);
        $enabled  = (bool)($settings['enabled'] ?? true);
        $note     = (string)($settings['note'] ?? '');

        $rolesActive = function_exists('ppc_user_can');
        ?>
        <!doctype html><html lang="de"><head>
            <meta charset="utf-8">
            <title>Module: <?=$moduleName?> – Einstellungen</title>
            <link rel="stylesheet" href="/assets/style.css">
        </head><body class="ppc-container">
            <h1>Module: <?=$moduleName?> – Einstellungen</h1>

            <?php if (!$rolesActive): ?>
                <p class="ppc-muted">Hinweis: Das Rollen-/Capability-System ist aktuell nicht aktiv geladen. Die Seite ist offen,
                    aber Cap-Checks greifen erst, wenn das Rollen-Modul aktiviert/geladen ist.</p>
            <?php endif; ?>

            <form method="post" action="/backend/modules/<?=$moduleName?>/controller.php?action=settings_save">
                <input type="hidden" name="modules_csrf" value="<?=e($token)?>">
                <div class="ppc-field">
                    <label>
                        <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                        Modul intern aktiv (modulspezifisch)
                    </label>
                </div>

                <div class="ppc-field">
                    <label for="note">Notiz (frei):</label>
                    <textarea id="note" name="note" rows="4" placeholder="Kurze Notiz für dieses Modul..."><?=e($note)?></textarea>
                </div>

                <div class="ppc-actions">
                    <button type="submit">Speichern</button>
                    <a class="ppc-button-secondary" href="/backend/">Zurück zum Admin-Dashboard</a>
                </div>
            </form>
        </body></html>
        <?php
        break;

    case 'settings_save':
        if (function_exists('ppc_require_cap')) {
            ppc_require_cap('manage_modules');
        }

        $cookie = (string)($_COOKIE[$csrfCookie] ?? '');
        $posted = (string)($_POST['modules_csrf'] ?? '');
        if (!$cookie || !$posted || !hash_equals($cookie, $posted)) {
            http_response_code(400);
            echo "CSRF-Überprüfung fehlgeschlagen.";
            exit;
        }

        $enabled = isset($_POST['enabled']);
        $note    = trim((string)($_POST['note'] ?? ''));

        $settings = [
            'enabled' => $enabled,
            'note'    => $note,
            'updated' => date('c'),
            'by'      => ppc_current_user(),
        ];

        $ok = tpl_write_settings($settingsFile, $settings);
        if (!$ok) {
            http_response_code(500);
            echo "Speichern fehlgeschlagen.";
            exit;
        }

        ppc_redirect('/backend/modules/' . $moduleName . '/controller.php?action=settings');
        break;

    default:
        http_response_code(404);
        ppc_json(['ok' => false, 'error' => 'Unknown action'], 404);
}
