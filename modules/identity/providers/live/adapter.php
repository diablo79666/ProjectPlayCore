<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../database/db.php';
require_once __DIR__ . '/../../../../core/utils.php';
require_once __DIR__ . '/../../settings.php';

// kleine Helfer
function idp_log(string $m): void {
    $dir = PPC_STORAGE . '/logs'; @mkdir($dir,0775,true);
    @file_put_contents($dir.'/kyc_live.log','['.date('c')."] $m\n",FILE_APPEND);
}
function idp_http_post_json(string $url, array $headers, array $payload): array {
    $ch=curl_init($url);
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>array_merge(['Content-Type: application/json'],$headers),
        CURLOPT_POSTFIELDS=>json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT=>30,
    ]);
    $raw=curl_exec($ch); $err=curl_error($ch);
    $code=(int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    if($raw===false) throw new RuntimeException("HTTP error: $err");
    $j=json_decode($raw,true);
    if(!is_array($j)) throw new RuntimeException("Invalid JSON ($code): $raw");
    return ['code'=>$code,'json'=>$j,'raw'=>$raw];
}
function idp_json_get(array $a,string $path){
    $cur=$a; foreach(explode('.', $path) as $seg){ if(!is_array($cur)||!array_key_exists($seg,$cur)) return null; $cur=$cur[$seg]; }
    return $cur;
}

// START
function idp_start(string $username): void {
    if ($username===''){ http_response_code(401); exit('Unauthorized'); }

    $api   = identity_setting_get('api_url_create','');
    $key   = identity_setting_get('api_key','');

    if ($api==='' || $key===''){ idp_log('missing api_url_create/api_key'); http_response_code(500); echo 'KYC-Start nicht konfiguriert.'; return; }

    $base = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http').'://'.($_SERVER['HTTP_HOST']??'');
    $cb = $base.'/backend/modules/identity/controller.php?action=kyc_callback';
    $wh = $base.'/backend/modules/identity/controller.php?action=kyc_webhook';

    try{
        $res = idp_http_post_json($api, ['Authorization: Bearer '.$key], [
            'externalUserId'=>$username, 'returnUrl'=>$cb, 'webhookUrl'=>$wh
        ]);
        idp_log("create code=".$res['code']." body=".$res['raw']);
        $url = idp_json_get($res['json'],'data.url') ?? idp_json_get($res['json'],'url');
        if(!$url) throw new RuntimeException('Hosted URL fehlt');
        header('Location: '.$url, true, 302); exit;
    } catch(Throwable $t){
        idp_log('create-error: '.$t->getMessage()); http_response_code(500); echo 'KYC-Start fehlgeschlagen.'; 
    }
}

// CALLBACK (nur Info)
function idp_callback(string $username, array $query): void {
    header('Content-Type: text/html; charset=utf-8'); ?>
<!doctype html><html lang="de"><head><meta charset="utf-8">
<title>Ausweisprüfung – weiter</title><link rel="stylesheet" href="/assets/style.css"></head>
<body class="ppc-container"><div class="wrap" style="max-width:760px;margin:2rem auto">
  <div class="card" style="background:#0c0c0c;border:1px solid #222;border-radius:12px;padding:14px">
    <h1>Ausweisprüfung</h1>
    <p>Danke, <strong><?= e($username) ?></strong>. Der Anbieter meldet das Ergebnis per Webhook.</p>
    <p><a class="ppc-button" href="/backend/modules/identity/controller.php?action=index">Zum Prüfstatus</a></p>
  </div>
</div></body></html><?php exit;
}

// WEBHOOK (HMAC-Validierung + Schreiben)
function idp_webhook(): void {
    $raw = file_get_contents('php://input') ?: '';
    $secret = identity_setting_get('webhook_secret','');
    $hdrName= identity_setting_get('webhook_sig_header','X-Signature');
    $sig = $_SERVER['HTTP_'.strtoupper(str_replace('-','_',$hdrName))] ?? '';

    if($secret===''){ http_response_code(400); echo 'no_secret'; return; }

    // HMAC-SHA256 über Raw-Body (base64)
    $calc = base64_encode(hash_hmac('sha256',$raw,$secret,true));
    if(!$sig || !hash_equals($calc,(string)$sig)){ idp_log('bad signature'); http_response_code(400); echo 'bad_signature'; return; }

    $j = json_decode($raw,true);
    if(!is_array($j)){ http_response_code(400); echo 'bad_json'; return; }

    $pStatus = identity_setting_get('jsonpath_status','status');
    $pDob    = identity_setting_get('jsonpath_dob','result.dob');
    $pRef    = identity_setting_get('jsonpath_ref','id');

    $status = strtolower((string)(idp_json_get($j,$pStatus) ?? ''));
    $dob    = (string)(idp_json_get($j,$pDob) ?? '');
    $ref    = (string)(idp_json_get($j,$pRef) ?? '');

    $user = (string)(idp_json_get($j,'externalUserId') ?? idp_json_get($j,'data.externalUserId') ?? '');
    if ($user===''){ http_response_code(400); echo 'missing_user'; return; }

    $mapped='pending';
    if(in_array($status,['approved','completed','verified'],true)) $mapped='verified';
    if(in_array($status,['rejected','failed','declined'],true))   $mapped='failed';

    $prov = identity_setting_get('provider_name','kyc');

    $db = ppc_db();
    try{
        $db->beginTransaction();
        $db->prepare("
            UPDATE users
               SET kyc_status=:s,
                   dob=CASE WHEN :dob<>'' THEN :dob ELSE dob END,
                   kyc_provider=:p, kyc_ref=:r
             WHERE username=:u
        ")->execute([':s'=>$mapped, ':dob'=>$dob, ':p'=>$prov, ':r'=>$ref, ':u'=>$user]);
        $db->commit();
        http_response_code(200); echo 'ok';
    }catch(Throwable $t){
        if($db->inTransaction()) $db->rollBack();
        idp_log('db-error: '.$t->getMessage()); http_response_code(500); echo 'error';
    }
}
