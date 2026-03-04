<?php
/**
 * Proxy pour afficher les logos (Wikipedia, etc.) en même origine.
 * Évite les blocages en iframe / CORS / referrer.
 * Usage : logo-proxy.php?u=BASE64_URL
 */
$raw = $_GET['u'] ?? '';
if ($raw === '') {
    http_response_code(400);
    exit;
}
$url = @base64_decode(str_replace(['-', '_'], ['+', '/'], $raw), true);
if ($url === false || $url === '') {
    http_response_code(400);
    exit;
}
$allowed = ['upload.wikimedia.org', 'commons.wikimedia.org', 'en.wikipedia.org', 'static.wikia.nocookie.net', 'a.espncdn.com'];
$host = parse_url($url, PHP_URL_HOST);
if (!in_array($host, $allowed, true)) {
    http_response_code(403);
    exit;
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT => 'StratEdgePronos/1.0 (https://stratedgepronos.fr)',
]);
$body = curl_exec($ch);
$ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200 || $body === false || strlen($body) < 100) {
    http_response_code(404);
    exit;
}

if (strpos($ct, 'image/') === 0) {
    header('Content-Type: ' . $ct);
} else {
    header('Content-Type: image/png');
}
header('Cache-Control: public, max-age=86400');
echo $body;
