<?php
/**
 * PPC Widget – Identity/KYC Toggle
 * - Direkt aufrufbar: /backend/widgets/identity_toggle.php  (mit CSRF & Rechten)
 * - Einbettbar im Dashboard: define('PPC_WIDGET_EMBED', true); include ...
 *
 * Rechte (eine Bedingung genügt):
 *  - ppc_user_can('view_admin') ODER Rolle 'admin' ODER Rolle 'superadmin'
 *
 * Speichert Flag in Tabelle identity_settings (key/value).
 */

declare(strict_types=1);

// ---------------- Common bootstrap ----------------
$__embed = defined('PPC_WIDGET_EMBED') && PPC_WIDGET_EMBED === true;

// Nur im Standalone-Modus Security+Login prüfen.
// Im Embed-Modus übernimmt das umgebende Dashboard diese Checks.
if (!$__embed) {
    require_once __DIR__ . '/../core/session.php';
    require_once __DIR__ . '/../core/security.php';
    require_once __DIR__ . '/../core/utils.php';
    require_once __DIR__ . '/../database/db.php';
    require_once __DIR__ . '/../../config.php';
    @require_once __DIR__ . '/../modules/roles/loader.php';

    ppc_security_headers();
    ppc_require_login();

    $db   = ppc_db();
    $user = (string)(ppc_current_user() ?? '');
} else {
    // Im Embed erwarten wir, dass $db, $user, e(), ppc_user_can etc. bereits existieren.
    if (!isset($db) || !($db instanceof PDO)) { $db = ppc_db(); }
    if (!isset($user) || !is_string($user) || $user==='') { $user = (string)(ppc_current_user() ?? ''); }
}

// ---------------- Rechte prüfen -------------------
$allowed = false;
try {
    if (function_exists('ppc_user_can') && ppc_user_can('view_admin', $user)) $allowed = true;
    if (!$allowed && function_exists('ppc_has_role') && ppc_has_role('admin', $user)) $allowed = true;
    if (!$allowed && function_exists('ppc_has_role') && ppc_has_role('superadmin', $user)) $allowed = true;
} catch (Throwable $t) {}

if (!$allowed) {
    if ($__embed) {
        // Im Embed: leise nichts anzeigen
        echo '<div class="card"><strong>Zugriff verweigert</strong> (Admin erforderlich)</div>';
        return;
    }
    http_response_code(403);
    echo '403 – view_admin erforderlich';
    exit;
}

// ---------------- Helpers (DB key/value) ----------
function idset_get(PDO $db, string $k, string $def=''): string {
    $db->exec("CREATE TABLE IF NOT EXISTS identity_settings (
        skey   VARCHAR(64) PRIMARY KEY,
        svalue TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $st = $db->prepare("SELECT svalue FROM identity_settings WHERE skey=:k LIMIT 1");
    $st->execute([':k'=>$k]);
    $v = $st->fetchColumn();
    return ($v !== false) ? (string)$v : $def;
}
function idset_set(PDO $db, string $k, string $v): void {
    $st = $db->prepare("INSERT INTO identity_settings(skey,svalue) VALUES(:k,:v)
                        ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)");
    $st->execute([':k'=>$k, ':v'=>$v]);
}

// ---------------- CSRF ----------------------------
$csrf_name = 'identity_toggle_csrf';
if (!$__embed) {
    $csrf = bin2hex(random_bytes(16));
    setcookie($csrf_name, $csrf, [
        'expires'=>time()+3600,'path'=>'/','secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'),
        'httponly'=>false,'samesite'=>'Lax',
    ]);
} else {
    // Im Embed generieren wir einen Token pro Render (kein Header-Set nötig)
    $csrf = bin2hex(random_bytes(16));
    $_COOKIE[$csrf_name] = $csrf;
}

// ---------------- Toggle-Action -------------------
$note=''; $err='';
$enabled = idset_get($db, 'enabled', '1'); // default: an
$provider = idset_get($db, 'provider', 'demo');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        $cookie = (string)($_COOKIE[$csrf_name] ?? '');
        $posted = (string)($_POST['csrf'] ?? '');
        if (!$cookie || !$posted || !hash_equals($cookie, $posted)) {
            throw new RuntimeException('CSRF ungültig.');
        }
        $new = ((string)($_POST['set_enabled'] ?? '')) === '1' ? '1' : '0';
        idset_set($db, 'enabled', $new);
        $enabled = $new;
        $note = $enabled === '1' ? 'KYC aktiviert.' : 'KYC deaktiviert.';
        if (!$__embed) {
            // Nach POST im Standalone erneut per GET anzeigen
            header('Location: '.$_SERVER['REQUEST_URI'], true, 303);
            exit;
        }
    } catch (Throwable $t) {
        $err = $t->getMessage();
    }
}

// ---------------- Render --------------------------
?>
<div class="card">
  <h3>Identitätsprüfung (KYC)</h3>
  <p>Status:
    <?php if ($enabled === '1'): ?>
      <span class="badge ok">AKTIV</span>
    <?php else: ?>
      <span class="badge err">DEAKTIV</span>
    <?php endif; ?>
    <small>Provider: <code><?= e($provider ?: '—') ?></code></small>
  </p>

  <?php if ($note): ?><div class="ppc-flash ok"><?= e($note) ?></div><?php endif; ?>
  <?php if ($err):  ?><div class="ppc-flash err"><?= e($err)  ?></div><?php endif; ?>

  <form method="post" style="margin-top:.5rem">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <?php if ($enabled === '1'): ?>
      <input type="hidden" name="set_enabled" value="0">
      <button class="ppc-button-secondary" type="submit">KYC deaktivieren</button>
    <?php else: ?>
      <input type="hidden" name="set_enabled" value="1">
      <button class="ppc-button" type="submit">KYC aktivieren</button>
    <?php endif; ?>
    <a class="ppc-button-small" href="/backend/modules/identity/admin.php">Provider-Einstellungen</a>
  </form>
</div>

<style>
.card{border:1px solid #222;border-radius:12px;padding:14px;background:#0c0c0c;margin-bottom:12px}
.badge{display:inline-block;padding:.1rem .5rem;border:1px solid #555;border-radius:999px;font-size:.85rem}
.badge.ok{color:#9fe3bd;border-color:#3da86b}
.badge.err{color:#f3a1a1;border-color:#b55d5d}
.ppc-flash{margin:.5rem 0;padding:.6rem .8rem;border-radius:6px;}
.ppc-flash.ok{background:#e7f7ee;border:1px solid #6cc799;}
.ppc-flash.err{background:#fdecea;border:1px solid #f1998e;}
</style>
<?php
// Ende Widget
