<?php
// Script ping sitemap a Google + Bing (a lancer manuellement apres chaque mise a jour majeure)
require_once __DIR__ . '/../includes/auth.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');

$sitemapUrl = 'https://stratedgepronos.fr/sitemap.xml';
$targets = [
    'Google' => 'https://www.google.com/ping?sitemap=' . urlencode($sitemapUrl),
    'Bing'   => 'https://www.bing.com/ping?sitemap=' . urlencode($sitemapUrl),
];

echo "=== PING SITEMAP ===\n\n";
foreach ($targets as $name => $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'StratEdge-Bot/1.0',
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $status = ($code === 200) ? '✅' : '⚠️';
    echo "$status $name: HTTP $code\n";
}

echo "\n=== SITEMAP CHECK ===\n";
$headers = @get_headers($sitemapUrl);
echo ($headers && strpos($headers[0], '200') !== false)
    ? "✅ sitemap.xml accessible: " . $headers[0] . "\n"
    : "❌ sitemap.xml inaccessible\n";

echo "\n=== ROBOTS.TXT CHECK ===\n";
$headers = @get_headers('https://stratedgepronos.fr/robots.txt');
echo ($headers && strpos($headers[0], '200') !== false)
    ? "✅ robots.txt accessible: " . $headers[0] . "\n"
    : "❌ robots.txt inaccessible\n";

echo "\n=== FIN ===\n";
echo "Pour une indexation plus rapide: passe aussi par Google Search Console\n";
echo "https://search.google.com/search-console/sitemaps?resource_id=https://stratedgepronos.fr/\n";
