<?php
/**
 * StratEdge — Fallback logos clubs (football, etc.) via Wikipedia API.
 * Utilisé quand Claude ne fournit pas team1_logo / team2_logo.
 * Résultats mis en cache fichier pour limiter les appels API.
 */

if (!function_exists('stratedge_fetch_team_logo_url')) {

function stratedge_fetch_team_logo_url($teamName) {
    $teamName = trim((string) $teamName);
    if ($teamName === '') return '';

    $cacheDir = dirname(__DIR__) . '/assets/logos-cache';
    $key = md5(strtolower($teamName));
    $cacheFile = $cacheDir . '/' . $key . '.url';

    if (is_file($cacheFile)) {
        $url = trim(@file_get_contents($cacheFile));
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) return $url;
    }

    $url = _stratedge_wikipedia_logo($teamName);
    if ($url !== '') {
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
        @file_put_contents($cacheFile, $url);
    }
    return $url;
}

function _stratedge_wikipedia_logo($teamName) {
    $search = $teamName;
    if (!preg_match('/\b(FC|F\.C\.|CF|football|club|United|City|Real|Atletico|Sporting|RDC|AC|AS|SC|SV)\b/i', $teamName)) {
        $search = $teamName . ' football club';
    }
    $apiBase = 'https://en.wikipedia.org/w/api.php';

    $opensearchUrl = $apiBase . '?action=opensearch&search=' . urlencode($search) . '&limit=5&format=json';
    $json = _stratedge_curl($opensearchUrl);
    if (!$json) return '';

    $data = @json_decode($json, true);
    if (!is_array($data) || empty($data[1])) return '';

    $titles = $data[1];
    foreach ($titles as $title) {
        $titleEnc = str_replace(' ', '%20', $title);
        $imgUrl = $apiBase . '?action=query&titles=' . $titleEnc . '&prop=pageimages&format=json&pithumbsize=120&piprop=thumbnail';
        $imgJson = _stratedge_curl($imgUrl);
        if (!$imgJson) continue;

        $imgData = @json_decode($imgJson, true);
        $pages = $imgData['query']['pages'] ?? [];
        foreach ($pages as $p) {
            $src = $p['thumbnail']['source'] ?? '';
            if ($src !== '' && preg_match('#^https?://#', $src)) return $src;
        }
    }
    return '';
}

function _stratedge_curl($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'StratEdgePronos/1.0 (https://stratedgepronos.fr)',
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result ?: null;
}

}
