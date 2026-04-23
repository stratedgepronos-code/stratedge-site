<?php
/**
 * STRATEDGE — Photos joueurs (lazy-load avec cache)
 *
 * Sources:
 *   NBA : cdn.nba.com/headshots/nba/latest/260x190/{player_id}.png
 *   NHL : assets.nhle.com/mugs/nhl/latest/{player_id}.png
 *   MLB : securea.mlb.com/mlb/images/players/head_shot/{player_id}.jpg
 *   SOCCER : media.api-sports.io/football/players/{player_id}.png
 *   TENNIS / GOLF / MMA : scraping à la demande (fallback)
 *
 * Usage:
 *   stratedge_player_photo(2544, 'nba')            → LeBron James
 *   stratedge_player_photo(592450, 'mlb')          → Aaron Judge
 *   stratedge_player_photo(8480824, 'nhl')         → Connor McDavid
 *   stratedge_player_photo(276, 'soccer')          → Haaland
 *
 * Le helper télécharge le fichier UNE FOIS puis le sert depuis le cache local.
 * Cache path: /assets/players/{sport}/{player_id}.{ext}
 */

if (!function_exists('stratedge_player_photo')) {

function stratedge_player_photo($playerId, string $sport = 'nba', int $ttlDays = 30): string {
    $playerId = trim((string)$playerId);
    if ($playerId === '' || !ctype_alnum(str_replace('-', '', $playerId))) return '';

    $sport = strtolower($sport);
    $validSports = ['nba', 'nhl', 'mlb', 'soccer', 'football'];
    if (!in_array($sport, $validSports, true)) return '';

    // Normalize soccer/football
    if ($sport === 'football') $sport = 'soccer';

    // Chemin local du cache
    $ext = ($sport === 'mlb') ? 'jpg' : 'png';
    $cacheDir = __DIR__ . '/../assets/players/' . $sport;
    $cacheFile = $cacheDir . '/' . $playerId . '.' . $ext;
    $cacheUrl = '/assets/players/' . $sport . '/' . $playerId . '.' . $ext;

    // Si en cache et récent, retourner directement
    $ttlSec = max(1, $ttlDays) * 86400;
    if (is_file($cacheFile) && filesize($cacheFile) > 1000) {
        $age = time() - filemtime($cacheFile);
        if ($age < $ttlSec) {
            return $cacheUrl;
        }
    }

    // Build URL source
    $sourceUrl = '';
    switch ($sport) {
        case 'nba':
            $sourceUrl = "https://cdn.nba.com/headshots/nba/latest/260x190/{$playerId}.png";
            break;
        case 'nhl':
            $sourceUrl = "https://assets.nhle.com/mugs/nhl/latest/{$playerId}.png";
            break;
        case 'mlb':
            $sourceUrl = "https://securea.mlb.com/mlb/images/players/head_shot/{$playerId}.jpg";
            break;
        case 'soccer':
            $sourceUrl = "https://media.api-sports.io/football/players/{$playerId}.png";
            break;
    }

    if ($sourceUrl === '') return '';

    // Download avec timeout court
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);

    $ch = curl_init($sourceUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_USERAGENT => 'Mozilla/5.0 StratEdge/1.0',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && $body !== false && strlen($body) > 1000) {
        if (@file_put_contents($cacheFile, $body) !== false) {
            return $cacheUrl;
        }
    }

    // Si téléchargement raté et fichier existe (même périmé), retourner le périmé
    if (is_file($cacheFile) && filesize($cacheFile) > 1000) {
        return $cacheUrl;
    }

    // Pas de fallback vers URL source si le serveur a retourné 403/404/etc.
    // (le navigateur échouera probablement aussi, laissant une image cassée
    //  et masquant la mascotte de fallback côté template).
    // On ne tente le fallback que si curl a échoué pour une raison réseau (code 0)
    if ($code === 0 && $sourceUrl !== '') {
        return $sourceUrl;
    }

    return '';
}

/**
 * Pré-chargement en batch (optionnel) pour une liste de (player_id, sport)
 * Utilisé par ex. en début de saison pour anticiper les pronos pro tennis/NBA/etc.
 *
 * stratedge_preload_player_photos([
 *   ['id' => 2544, 'sport' => 'nba'],
 *   ['id' => 276, 'sport' => 'soccer'],
 * ]);
 */
function stratedge_preload_player_photos(array $players): array {
    $results = ['ok' => 0, 'cached' => 0, 'failed' => 0];
    foreach ($players as $p) {
        if (!isset($p['id'], $p['sport'])) continue;
        $before = is_file(__DIR__ . '/../assets/players/' . strtolower($p['sport']) . '/' . $p['id'] . '.png')
               || is_file(__DIR__ . '/../assets/players/' . strtolower($p['sport']) . '/' . $p['id'] . '.jpg');
        $url = stratedge_player_photo($p['id'], $p['sport']);
        $after = strpos($url, '/assets/') === 0;
        if ($before) $results['cached']++;
        elseif ($after) $results['ok']++;
        else $results['failed']++;
    }
    return $results;
}

}
