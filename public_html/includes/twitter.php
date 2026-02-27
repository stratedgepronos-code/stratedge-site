<?php
// ============================================================
// STRATEDGE — Module Twitter/X Auto-Post
// OAuth 1.0a — clés passées en paramètre (pas de define/constante)
// ============================================================

function twitterPhrase(string $type, string $titre = ''): string {
    $types     = explode(',', $type);
    $principal = trim($types[0]);
    $phrases = [
        'safe' => [
            "🛡️ Nouveau bet SAFE vient d'être posté !\n\n🔒 Analyse complète réservée aux abonnés\n👉 stratedgepronos.fr",
            "📊 Un bet SAFE est disponible !\n\n🔒 Card visible pour les abonnés uniquement\n💰 stratedgepronos.fr",
            "🛡️ BET SAFE du jour !\n\n🔒 Réservé aux membres\n👉 stratedgepronos.fr",
        ],
        'live' => [
            "⚡ BET LIVE EN COURS — AGIS MAINTENANT !\n\n🔒 Accès immédiat sur stratedgepronos.fr\n⏱️ Ne rate pas cette opportunité !",
            "🔴 LIVE BET posté — temps limité !\n\n⚡ Analyse disponible pour les abonnés\n👉 stratedgepronos.fr",
        ],
        'fun' => [
            "🎯 Un BET FUN vient d'être posté !\n\n🔒 Pour les amateurs de sensations fortes\n👉 stratedgepronos.fr",
            "🎲 Bet FUN disponible !\n\n🔒 Grosse cote réservée aux abonnés\n💥 stratedgepronos.fr",
        ],
    ];
    $pool   = $phrases[$principal] ?? $phrases['safe'];
    $phrase = $pool[array_rand($pool)];
    if ($titre) $phrase = "📌 " . $titre . "\n\n" . $phrase;
    return $phrase;
}

function genererImageLocked(string $cheminOriginal, string $ext): ?string {
    if (!function_exists('imagecreatefrompng')) return null;
    $ext = strtolower($ext);
    $img = match($ext) {
        'jpg','jpeg' => @imagecreatefromjpeg($cheminOriginal),
        'png'        => @imagecreatefrompng($cheminOriginal),
        'webp'       => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($cheminOriginal) : null,
        'gif'        => @imagecreatefromgif($cheminOriginal),
        default      => null,
    };
    if (!$img) return null;
    $w = imagesx($img); $h = imagesy($img);
    for ($i = 0; $i < 20; $i++) imagefilter($img, IMG_FILTER_GAUSSIAN_BLUR);
    imagefilter($img, IMG_FILTER_BRIGHTNESS, -20);
    $overlay = imagecreatetruecolor($w, $h);
    imagefill($overlay, 0, 0, imagecolorallocate($overlay, 0, 0, 0));
    imagecopymerge($img, $overlay, 0, 0, 0, 0, $w, $h, 55);
    imagedestroy($overlay);
    $cx = (int)($w/2); $cy = (int)($h/2);
    $scale = max(1, (int)(min($w,$h)/180));
    $blanc = imagecolorallocate($img,255,255,255);
    $blancDim = imagecolorallocate($img,200,200,200);
    $rose = imagecolorallocate($img,255,45,120);
    imagefilledrectangle($img,$cx-28*$scale,$cy-5*$scale,$cx+28*$scale,$cy+28*$scale,$rose);
    imagearc($img,$cx,$cy-8*$scale,32*$scale,40*$scale,180,360,$blanc);
    imagearc($img,$cx,$cy-8*$scale,28*$scale,36*$scale,180,360,$blanc);
    imagefilledellipse($img,$cx,$cy+6*$scale,10*$scale,10*$scale,$blanc);
    imagefilledrectangle($img,$cx-2*$scale,$cy+6*$scale,$cx+2*$scale,$cy+16*$scale,$blanc);
    $fs = max(2,$scale+1);
    $txt = 'RESERVES AUX ABONNES';
    imagestring($img,$fs,$cx-(int)(strlen($txt)*imagefontwidth($fs)/2),$cy+38*$scale,$txt,$blanc);
    $sub = 'stratedgepronos.fr';
    imagestring($img,$fs,$cx-(int)(strlen($sub)*imagefontwidth($fs)/2),$cy+38*$scale+imagefontheight($fs)+2,$sub,$blancDim);
    $tmpPath = sys_get_temp_dir().'/stratedge_locked_'.time().'_'.rand(100,999).'.jpg';
    imagejpeg($img,$tmpPath,90);
    imagedestroy($img);
    return $tmpPath;
}

function buildOAuthHeader(string $method, string $url, array $bodyParams,
                          string $apiKey, string $apiSecret,
                          string $token, string $tokenSecret): string {
    $oauth = [
        'oauth_consumer_key'     => $apiKey,
        'oauth_nonce'            => bin2hex(random_bytes(16)),
        'oauth_signature_method' => 'HMAC-SHA1',
        'oauth_timestamp'        => (string)time(),
        'oauth_token'            => $token,
        'oauth_version'          => '1.0',
    ];

    // Pour la signature : oauth params + body params (form) fusionnés
    $toSign = array_merge($oauth, $bodyParams);
    $encoded = [];
    foreach ($toSign as $k => $v) {
        $encoded[rawurlencode((string)$k)] = rawurlencode((string)$v);
    }
    ksort($encoded);
    $paramParts = [];
    foreach ($encoded as $k => $v) $paramParts[] = $k . '=' . $v;

    $base    = strtoupper($method).'&'.rawurlencode($url).'&'.rawurlencode(implode('&',$paramParts));
    $sigKey  = rawurlencode($apiSecret).'&'.rawurlencode($tokenSecret);
    $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1',$base,$sigKey,true));

    ksort($oauth);
    $parts = [];
    foreach ($oauth as $k => $v) $parts[] = rawurlencode($k).'="'.rawurlencode($v).'"';
    return 'OAuth '.implode(', ',$parts);
}

function twitterUploadMedia(string $imagePath, array $keys): ?string {
    if (!file_exists($imagePath)) return null;
    $url       = 'https://upload.twitter.com/1.1/media/upload.json';
    $imageData = base64_encode(file_get_contents($imagePath));
    $auth = buildOAuthHeader('POST',$url,['media_data'=>$imageData],
        $keys['api_key'],$keys['api_secret'],$keys['access_token'],$keys['access_token_secret']);
    $ch = curl_init($url);
    curl_setopt_array($ch,[
        CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>['media_data'=>$imageData],
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['Authorization: '.$auth],
        CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_TIMEOUT=>60,
    ]);
    $response = curl_exec($ch); $code = curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code !== 200) return null;
    return json_decode($response,true)['media_id_string'] ?? null;
}

function twitterPostTweet(string $texte, array $keys, ?string $mediaId = null): array {
    $url  = 'https://api.twitter.com/2/tweets';
    $body = ['text' => $texte];
    if ($mediaId) $body['media'] = ['media_ids' => [$mediaId]];

    // JSON body → body params PAS dans la signature OAuth
    $auth = buildOAuthHeader('POST',$url,[],
        $keys['api_key'],$keys['api_secret'],$keys['access_token'],$keys['access_token_secret']);

    $ch = curl_init($url);
    curl_setopt_array($ch,[
        CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode($body),
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>['Authorization: '.$auth,'Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_TIMEOUT=>20,
    ]);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch,CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($code === 201) return ['success'=>true,'tweet_id'=>json_decode($response,true)['data']['id']??''];
    return ['success'=>false,'error'=>$err?:$response,'http_code'=>$code];
}

function posterBetSurTwitter(string $imagePath, string $type, array $keys, string $titre = ''): array {
    if (empty($keys['api_key']) || empty($keys['access_token']))
        return ['success'=>false,'error'=>'Clés API non configurées'];
    $ext     = strtolower(pathinfo($imagePath,PATHINFO_EXTENSION));
    $tmpPath = genererImageLocked($imagePath,$ext);
    $mediaId = null;
    if ($tmpPath) { $mediaId = twitterUploadMedia($tmpPath,$keys); @unlink($tmpPath); }
    return twitterPostTweet(twitterPhrase($type,$titre),$keys,$mediaId);
}
