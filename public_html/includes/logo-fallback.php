<?php
/**
 * StratEdge — Fallback logos clubs (foot, NBA, NHL, MLB, tennis).
 * Cascade : TheSportsDB → ESPN → Wikipedia → '' (vide)
 * Cache fichier 30 jours pour limiter les appels API.
 */

if (!function_exists('stratedge_fetch_team_logo_url')) {

function stratedge_fetch_team_logo_url($teamName, $sport = 'football') {
    $teamName = trim((string) $teamName);
    if ($teamName === '') return '';

    // ═══════════════════════════════════════════════════════
    // PRIORITÉ 0 : Base PHP football (200+ clubs, IDs vérifiés)
    // ═══════════════════════════════════════════════════════
    if (strtolower($sport) === 'football') {
        require_once __DIR__ . '/football-logos-db.php';
        $fbLogo = stratedge_football_logo($teamName);
        if ($fbLogo !== '') return $fbLogo;
    }

    // ═══════════════════════════════════════════════════════
    // PRIORITÉ 1 : Base locale fichiers PNG (mapping.json)
    // ═══════════════════════════════════════════════════════
    require_once __DIR__ . '/team-logos-db.php';
    $local = stratedge_local_team_logo($teamName, $sport);
    if ($local !== '') return $local;

    // ═══════════════════════════════════════════════════════
    // PRIORITÉ 2+ : APIs externes avec cache (cascade)
    // ═══════════════════════════════════════════════════════
    $cacheDir = dirname(__DIR__) . '/assets/logos-cache';
    $key = md5(strtolower($teamName) . '_' . $sport);
    $cacheFile = $cacheDir . '/' . $key . '.url';

    // Cache valide 30 jours
    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 2592000) {
        $url = trim(@file_get_contents($cacheFile));
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) return $url;
    }

    // Cascade d'APIs
    $url = _stratedge_thesportsdb_logo($teamName, $sport);
    if ($url === '') $url = _stratedge_espn_logo($teamName, $sport);
    if ($url === '') $url = _stratedge_wikipedia_logo($teamName);

    if ($url !== '') {
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
        @file_put_contents($cacheFile, $url);
    }
    return $url;
}

// ─────────────────────────────────────────────────────────────
// SOURCE 1 : TheSportsDB (free API key '123')
// Couvre : foot mondial, NBA, NHL, MLB, NFL, tennis
// ─────────────────────────────────────────────────────────────
function _stratedge_thesportsdb_logo($teamName, $sport = 'football') {
    $url = 'https://www.thesportsdb.com/api/v1/json/123/searchteams.php?t=' . urlencode($teamName);
    $json = _stratedge_curl($url);
    if (!$json) return '';

    $data = @json_decode($json, true);
    if (!is_array($data) || empty($data['teams'])) return '';

    $sportMap = [
        'football' => 'Soccer',
        'basket'   => 'Basketball',
        'hockey'   => 'Ice Hockey',
        'baseball' => 'Baseball',
        'tennis'   => 'Tennis',
    ];
    $expectedSport = $sportMap[strtolower($sport)] ?? null;

    foreach ($data['teams'] as $team) {
        if ($expectedSport && isset($team['strSport']) && $team['strSport'] !== $expectedSport) {
            continue;
        }
        $badge = $team['strBadge'] ?? $team['strTeamBadge'] ?? '';
        if ($badge && filter_var($badge, FILTER_VALIDATE_URL)) {
            return $badge;
        }
    }

    // Fallback : premier résultat même si sport ne match pas
    $first = $data['teams'][0];
    $badge = $first['strBadge'] ?? $first['strTeamBadge'] ?? '';
    if ($badge && filter_var($badge, FILTER_VALIDATE_URL)) {
        return $badge;
    }
    return '';
}

// ─────────────────────────────────────────────────────────────
// SOURCE 2 : ESPN hidden API (no auth)
// Couvre : foot top leagues, NBA, NHL, MLB, NFL
// ─────────────────────────────────────────────────────────────
function _stratedge_espn_logo($teamName, $sport = 'football') {
    $sportPaths = [
        'football' => ['football/eng.1', 'football/esp.1', 'football/ita.1', 'football/ger.1', 'football/fra.1', 'football/uefa.champions'],
        'basket'   => ['basketball/nba'],
        'hockey'   => ['hockey/nhl'],
        'baseball' => ['baseball/mlb'],
    ];
    $paths = $sportPaths[strtolower($sport)] ?? ['football/eng.1'];
    $teamLower = strtolower(trim($teamName));

    foreach ($paths as $path) {
        $url = "https://site.api.espn.com/apis/site/v2/sports/{$path}/teams";
        $json = _stratedge_curl($url);
        if (!$json) continue;

        $data = @json_decode($json, true);
        $teams = $data['sports'][0]['leagues'][0]['teams'] ?? [];

        foreach ($teams as $teamWrap) {
            $team = $teamWrap['team'] ?? [];
            $names = [
                strtolower($team['displayName'] ?? ''),
                strtolower($team['shortDisplayName'] ?? ''),
                strtolower($team['name'] ?? ''),
                strtolower($team['nickname'] ?? ''),
                strtolower($team['location'] ?? ''),
            ];
            foreach ($names as $n) {
                if ($n === '') continue;
                if ($n === $teamLower || strpos($teamLower, $n) !== false || strpos($n, $teamLower) !== false) {
                    $logos = $team['logos'] ?? [];
                    if (!empty($logos[0]['href'])) return $logos[0]['href'];
                }
            }
        }
    }
    return '';
}

// ─────────────────────────────────────────────────────────────
// SOURCE 3 : Wikipedia (fallback ultime)
// ─────────────────────────────────────────────────────────────
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
        $titleEnc = rawurlencode(str_replace(' ', '_', $title));
        $imgUrl = $apiBase . '?action=query&titles=' . $titleEnc . '&prop=pageimages&format=json&pithumbsize=200&piprop=thumbnail';
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
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'StratEdgePronos/1.0 (https://stratedgepronos.fr)',
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result ?: null;
}

}
