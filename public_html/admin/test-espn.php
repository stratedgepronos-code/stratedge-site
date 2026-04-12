<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
header('Content-Type: text/plain; charset=utf-8');

$url = 'https://a.espncdn.com/i/teamlogos/soccer/500/160.png';
echo "Test URL: $url\n\n";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_USERAGENT => 'Mozilla/5.0',
    CURLOPT_VERBOSE => true,
    CURLOPT_STDERR => fopen('php://output', 'w'),
]);
$r = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "\n\n=== RESULT ===\n";
echo "HTTP Code: $code\n";
echo "Response size: " . strlen($r ?: '') . " bytes\n";
echo "curl_error: $err\n";
echo "First 200 bytes: " . substr($r ?: '', 0, 200) . "\n";
