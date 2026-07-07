<?php
// ============================================================
// STRATEDGE — Client X API v2 (OAuth 1.0a user context)
// Permet : poster un tweet AVEC image, et RÉPONDRE à un tweet
// (thread pick → résultat), choses impossibles via IFTTT.
// Config : includes/x_api_keys.local.php (JAMAIS versionné)
// ============================================================

function xApiConfig(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $f = __DIR__ . '/x_api_keys.local.php';
    $cfg = file_exists($f) ? (include $f) : [];
    if (!is_array($cfg)) $cfg = [];
    $cfg += ['actif' => false, 'consumer_key' => '', 'consumer_secret' => '',
             'access_token' => '', 'access_secret' => ''];
    return $cfg;
}

function xApiActif(): bool {
    $c = xApiConfig();
    return !empty($c['actif']) && $c['consumer_key'] !== '' && $c['access_token'] !== '';
}

/** En-tête Authorization OAuth 1.0a. $extraParams = params query/form URL-encodés
 *  à inclure dans la signature (PAS les corps JSON ni multipart). */
function xOauthHeader(string $method, string $url, array $extraParams = []): string {
    $c = xApiConfig();
    $oauth = [
        'oauth_consumer_key'     => $c['consumer_key'],
        'oauth_nonce'            => bin2hex(random_bytes(16)),
        'oauth_signature_method' => 'HMAC-SHA1',
        'oauth_timestamp'        => (string)time(),
        'oauth_token'            => $c['access_token'],
        'oauth_version'          => '1.0',
    ];
    $sigParams = array_merge($oauth, $extraParams);
    ksort($sigParams);
    $pairs = [];
    foreach ($sigParams as $k => $v) $pairs[] = rawurlencode($k) . '=' . rawurlencode((string)$v);
    $base = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode(implode('&', $pairs));
    $key  = rawurlencode($c['consumer_secret']) . '&' . rawurlencode($c['access_secret']);
    $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $base, $key, true));
    $h = [];
    foreach ($oauth as $k => $v) $h[] = rawurlencode($k) . '="' . rawurlencode((string)$v) . '"';
    return 'Authorization: OAuth ' . implode(', ', $h);
}

/** Poste un tweet. $replyToId = ID du tweet auquel répondre (thread).
 *  Retour : ['ok'=>bool, 'id'=>string|null, 'code'=>int, 'raw'=>string] */
function xApiPostTweet(string $text, ?string $replyToId = null, ?string $mediaId = null): array {
    $url  = 'https://api.twitter.com/2/tweets';
    $body = ['text' => $text];
    if ($replyToId) $body['reply'] = ['in_reply_to_tweet_id' => (string)$replyToId];
    if ($mediaId)   $body['media'] = ['media_ids' => [(string)$mediaId]];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [xOauthHeader('POST', $url), 'Content-Type: application/json'],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode((string)$resp, true);
    $id   = $json['data']['id'] ?? null;
    return ['ok' => ($code >= 200 && $code < 300 && $id), 'id' => $id, 'code' => $code, 'raw' => mb_substr((string)$resp, 0, 300)];
}

/** Upload d'une image locale → media_id à joindre au tweet. Null si échec (le
 *  tweet part alors sans image plutôt que d'échouer). */
function xApiUploadMedia(string $filePath): ?string {
    if (!is_file($filePath)) return null;
    $url = 'https://upload.twitter.com/1.1/media/upload.json';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => ['media' => new CURLFile($filePath)],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [xOauthHeader('POST', $url)],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode((string)$resp, true);
    return ($code >= 200 && $code < 300) ? ($json['media_id_string'] ?? null) : null;
}
