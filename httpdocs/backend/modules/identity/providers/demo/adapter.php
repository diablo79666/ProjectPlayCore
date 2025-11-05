<?php
/**
 * identity/providers/demo/adapter.php
 * Demo-Adapter mit HMAC-Webhook (bereit für echten Provider-Austausch)
 * Stellt folgende Funktions-Signaturen bereit (vom Controller aufgerufen):
 *   - identity_start(string $username): void
 *   - identity_callback(string $username, string $verificationId): void
 *   - identity_webhook(string $username, string $verificationId, string $status): void
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../database/db.php';
require_once __DIR__ . '/../../../../core/utils.php';
require_once __DIR__ . '/../../../../../config.php';

/* ============================== Logging =================================== */
function identity_demo_log(string $msg): void {
    $dir = PPC_STORAGE . '/logs';
    @mkdir($dir, 0775, true);
    @file_put_contents($dir . '/identity.log', '['.date('c')."] ".$msg.PHP_EOL, FILE_APPEND);
}
function identity_db(): PDO { return ppc_db(); }

/* ============================== Settings ================================== */
function identity_cfg(string $k, string $def=''): string {
    try {
        $db = identity_db();
        $db->exec("CREATE TABLE IF NOT EXISTS identity_settings (
            skey VARCHAR(64) PRIMARY KEY,
            svalue TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $st = $db->prepare("SELECT svalue FROM identity_settings WHERE skey=:k LIMIT 1");
        $st->execute([':k'=>$k]);
        $v = $st->fetchColumn();
        return ($v !== false) ? (string)$v : $def;
    } catch (Throwable $t) { return $def; }
}
function identity_webhook_secret(): string {
    $s = identity_cfg('webhook_secret','');
    if ($s!=='') return $s;
    if (defined('PPC_KYC_WEBHOOK_SECRET') && PPC_KYC_WEBHOOK_SECRET) return (string)PPC_KYC_WEBHOOK_SECRET;
    $env = getenv('PPC_WEBHOOK_SECRET');
    if (is_string($env) && $env!=='') return $env;
    return 'demo_secret_change_me';
}

/* ============================== State ===================================== */
function identity_state_path(string $u): string { return PPC_STORAGE.'/identity/state_'.sha1($u).'.json'; }
function identity_state_write(string $u, array $s): void {
    @mkdir(dirname(identity_state_path($u)),0775,true);
    @file_put_contents(identity_state_path($u), json_encode($s, JSON_UNESCAPED_UNICODE));
}
function identity_state_read(string $u): ?array {
    $p = identity_state_path($u);
    if (!is_file($p)) return null;
    $j = json_decode(@file_get_contents($p)?:'null', true);
    return is_array($j)?$j:null;
}
function identity_state_clear(string $u): void { @unlink(identity_state_path($u)); }

/* ============================== Helpers =================================== */
function identity_new_code(int $len=6): string {
    $chars='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $c='';
    for($i=0;$i<$len;$i++) $c.=$chars[random_int(0,strlen($chars)-1)];
    return $c;
}
function identity_payload(array $p): string {
    $order=['verification_id','status','code','liveness','facematch','ts'];
    $vals=[]; foreach($order as $k){ $vals[] = isset($p[$k])?(string)$p[$k]:''; }
    return implode('|',$vals);
}
function identity_sign(array $p, string $secret): string {
    return hash_hmac('sha256', identity_payload($p), $secret);
}

/* ============================== Actions =================================== */
function identity_start(string $username): void {
    if ($username===''){ http_response_code(401); exit('Unauthorized'); }

    $vid  = 'demo_'.bin2hex(random_bytes(6));
    $code = identity_new_code(6);
    identity_state_write($username, [
        'u'=>$username, 'v'=>$vid, 'code'=>$code, 't'=>time(), 'exp'=>time()+20*60
    ]);
    $cb = '/backend/modules/identity/controller.php?action=kyc_callback&verification_id='.rawurlencode($vid);
    header('Location: '.$cb, true, 302);
    exit;
}

function identity_callback(string $username, string $verificationId): void {
    if ($username===''){ http_response_code(401); exit('Unauthorized'); }
    $st = identity_state_read($username);
    $code = is_array($st)?(string)($st['code']??''):'';

    $params = [
        'verification_id'=>$verificationId,
        'status'         =>'approved',
        'code'           =>$code,
        'liveness'       =>'1',
        'facematch'      =>'1',
        'ts'             =>(string)time()
    ];
    $sig = identity_sign($params, identity_webhook_secret());

    $webhook =
        '/backend/modules/identity/controller.php?action=kyc_webhook'
        .'&verification_id='.rawurlencode($params['verification_id'])
        .'&status='.rawurlencode($params['status'])
        .'&code='.rawurlencode($params['code'])
        .'&liveness='.rawurlencode($params['liveness'])
        .'&facematch='.rawurlencode($params['facematch'])
        .'&ts='.rawurlencode($params['ts'])
        .'&sig='.rawurlencode($sig);

    header('Content-Type: text/html; charset=utf-8'); ?>
<!doctype html><html lang="de"><head>
<meta charset="utf-8"><title>Ausweisprüfung – Selfie mit Code</title>
<link rel="stylesheet" href="/assets/style.css">
<style>
.wrap{max-width:760px;margin:2rem auto;padding:1rem}
.card{border:1px solid #222;border-radius:12px;padding:14px;background:#0c0c0c;margin-bottom:12px}
.row{display:flex;gap:.5rem;flex-wrap:wrap}
.code{font-size:2rem;letter-spacing:.15rem;border:1px dashed #555;padding:.4rem .7rem;border-radius:8px;display:inline-block}
ul{margin:.4rem 0 .2rem 1.1rem}
small{color:#9aa}
</style>
</head><body class="ppc-container">
<div class="wrap">
  <div class="card">
    <h1>Ausweisprüfung – Schritt Selfie mit Code</h1>
    <p>Angemeldet als <strong><?= e($username) ?></strong></p>
    <p><strong>Dein Prüf-Code:</strong> <span class="code"><?= e($code ?: '—') ?></span></p>
    <p>Bitte führe jetzt die Prüfung durch:</p>
    <ul>
      <li>Vorder- und Rückseite deines Ausweises fotografieren.</li>
      <li>Obigen <strong>Code auf einen Zettel</strong> schreiben.</li>
      <li><strong>Selfie mit Zettel</strong> (Gesicht gut sichtbar) aufnehmen.</li>
    </ul>
    <p><small>Live übernimmt das ein KYC-Provider (Dok-Echtheit, Face-Match, Liveness). Wir speichern keine Bilder.</small></p>
    <div class="row" style="margin-top:.6rem">
      <a class="ppc-button" href="<?= e($webhook) ?>">Prüfung abschließen (Demo)</a>
      <a class="ppc-button-secondary" href="/user/dashboard.php">Zum Dashboard</a>
    </div>
  </div>
</div>
</body></html>
<?php
    exit;
}

function identity_webhook(string $username, string $verificationId, string $status): void {
    if ($username===''){ http_response_code(401); exit('Unauthorized'); }

    $db = identity_db();
    $st = identity_state_read($username);
    if (!$st || ($st['v']??null)!==$verificationId || (int)($st['exp']??0) < time()) {
        identity_demo_log("webhook invalid-state user={$username}");
        http_response_code(400); echo 'invalid_state'; return;
    }

    $vid = (string)($_GET['verification_id'] ?? $_POST['verification_id'] ?? '');
    $stat= (string)($_GET['status'] ?? $_POST['status'] ?? '');
    $code= (string)($_GET['code'] ?? $_POST['code'] ?? '');
    $liv = (string)($_GET['liveness'] ?? $_POST['liveness'] ?? '0');
    $fac = (string)($_GET['facematch'] ?? $_POST['facematch'] ?? '0');
    $ts  = (string)($_GET['ts'] ?? $_POST['ts'] ?? '');
    $sig = (string)($_GET['sig'] ?? $_POST['sig'] ?? '');

    if ($ts==='' || abs(time()-(int)$ts)>600) { http_response_code(401); echo 'stale_ts'; return; }

    $secret = identity_webhook_secret();
    $calc   = identity_sign([
        'verification_id'=>$vid,'status'=>$stat,'code'=>$code,'liveness'=>$liv,'facematch'=>$fac,'ts'=>$ts
    ], $secret);
    if (!hash_equals($calc,$sig)) {
        identity_demo_log("webhook bad-signature user={$username}");
        http_response_code(401); echo 'bad_signature'; return;
    }

    $codeOk = hash_equals((string)$st['code'], $code);
    $livOk  = ($liv==='1' || strtolower($liv)==='true');
    $facOk  = ($fac==='1' || strtolower($fac)==='true');

    if ($stat!=='approved' || !$codeOk || !$livOk || !$facOk) {
        try {
            $db->prepare("UPDATE users SET kyc_status='failed', kyc_provider='demo', kyc_ref=:r WHERE username=:u")
               ->execute([':r'=>$vid, ':u'=>$username]);
        } catch (Throwable $t) {}
        identity_demo_log("webhook failed user={$username} codeOk={$codeOk} live={$livOk} face={$facOk}");
        http_response_code(200); echo 'failed'; return;
    }

    try {
        $chk = $db->prepare("SELECT kyc_status FROM users WHERE username=:u LIMIT 1");
        $chk->execute([':u'=>$username]);
        $cur = $chk->fetch(PDO::FETCH_ASSOC);
        if ($cur && (string)$cur['kyc_status']==='verified') {
            identity_state_clear($username);
            http_response_code(204); return;
        }
    } catch (Throwable $t) {}

    $dob = date('Y-m-d', strtotime('-21 years')); // Demo: 21+, live: DOB aus Payload

    try {
        $db->beginTransaction();
        $db->prepare("UPDATE users
                         SET kyc_status='verified', dob=:dob, kyc_provider='demo', kyc_ref=:r
                       WHERE username=:u")
           ->execute([':dob'=>$dob, ':r'=>$vid, ':u'=>$username]);
        $db->commit();
        identity_state_clear($username);
        identity_demo_log("webhook ok user={$username} ref={$vid}");
        http_response_code(200); echo 'ok';
    } catch (Throwable $t) {
        if ($db->inTransaction()) $db->rollBack();
        identity_demo_log("webhook db-error user={$username} err=".$t->getMessage());
        http_response_code(500); echo 'error';
    }
}
