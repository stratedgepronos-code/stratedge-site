<?php
/**
 * Importe les matchs de foot pour une date donnée depuis API-Football ou Football-Data.org.
 *
 * @param PDO          $db
 * @param string       $targetDate    Date des matchs (YYYY-MM-DD)
 * @param string       $voteClosedAt  Datetime de fermeture des votes (YYYY-MM-DD HH:MM:SS)
 * @param DateTimeZone $tzParis
 * @return array ['inserted' => int, 'total' => int, 'source' => string, 'error' => string]
 */
function importFootballMatches(PDO $db, string $targetDate, string $voteClosedAt, DateTimeZone $tzParis): array
{
    $configPath = __DIR__ . '/football_data_config.php';
    $config = file_exists($configPath) ? (include $configPath) : [];
    if (!is_array($config)) $config = [];

    $apiFootballKey      = trim($config['api_football_key'] ?? '');
    $apiFootballRapidKey = trim($config['api_football_rapidapi_key'] ?? '');
    $footballDataKey     = trim($config['api_key'] ?? '');

    if ($apiFootballKey !== '' || $apiFootballRapidKey !== '') {
        $allowedLeagueIds = [];
        if (!empty($config['allowed_league_ids']) && is_array($config['allowed_league_ids'])) {
            foreach ($config['allowed_league_ids'] as $id) {
                $id = (int) $id;
                if ($id > 0) $allowedLeagueIds[] = $id;
            }
        }
        return importFromApiFootball($db, $targetDate, $voteClosedAt, $tzParis, $apiFootballKey, $apiFootballRapidKey, $allowedLeagueIds);
    }

    if ($footballDataKey !== '') {
        return importFromFootballData($db, $targetDate, $voteClosedAt, $tzParis, $footballDataKey);
    }

    return ['inserted' => 0, 'total' => 0, 'source' => '', 'error' => 'Aucune clé API configurée. Ajoute ta clé dans includes/football_data_config.php.'];
}

// ─────────────────────────────────────────────────────────────
// API-Football — toutes compétitions
// Supporte 2 modes d'accès :
//   1) Direct (api-sports.io) — header x-apisports-key
//   2) RapidAPI — header x-rapidapi-key + x-rapidapi-host
// ─────────────────────────────────────────────────────────────
function apiFootballRequest(string $url, array $headers, int $timeout = 20): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        if ($body === false || $body === '') {
            return ['ok' => false, 'error' => 'curl error: ' . ($curlErr ?: 'empty response') . ' (HTTP ' . $httpCode . ')'];
        }
        return ['ok' => true, 'body' => $body, 'http_code' => $httpCode];
    }

    $headerStr = implode("\r\n", $headers) . "\r\n";
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'header'        => $headerStr,
            'timeout'       => $timeout,
            'ignore_errors' => true,
        ]
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return ['ok' => false, 'error' => 'file_get_contents failed (SSL/réseau)'];
    }
    return ['ok' => true, 'body' => $body, 'http_code' => 200];
}

function importFromApiFootball(PDO $db, string $targetDate, string $voteClosedAt, DateTimeZone $tzParis, string $apiKeyDirect, string $apiKeyRapid = '', array $allowedLeagueIds = []): array
{
    $endpoints = [];
    if ($apiKeyDirect !== '') {
        $endpoints[] = [
            'url'     => 'https://v3.football.api-sports.io/fixtures?date=' . $targetDate,
            'headers' => ['x-apisports-key: ' . $apiKeyDirect],
            'label'   => 'direct (api-sports.io)',
        ];
    }
    if ($apiKeyRapid !== '') {
        $endpoints[] = [
            'url'     => 'https://api-football-v1.p.rapidapi.com/v3/fixtures?date=' . $targetDate,
            'headers' => ['x-rapidapi-key: ' . $apiKeyRapid, 'x-rapidapi-host: api-football-v1.p.rapidapi.com'],
            'label'   => 'RapidAPI',
        ];
    }
    if (empty($endpoints)) {
        return ['inserted' => 0, 'total' => 0, 'source' => 'API-Football', 'error' => 'Aucune clé API-Football configurée. Renseigne api_football_key (dashboard.api-football.com) ou api_football_rapidapi_key (RapidAPI).'];
    }

    $debugLog = [];
    $data = null;
    $usedLabel = '';

    foreach ($endpoints as $ep) {
        $result = apiFootballRequest($ep['url'], $ep['headers']);

        if (!$result['ok']) {
            $debugLog[] = $ep['label'] . ' → ' . $result['error'];
            continue;
        }

        $parsed = @json_decode($result['body'], true);

        if (!is_array($parsed)) {
            $debugLog[] = $ep['label'] . ' → JSON invalide (HTTP ' . ($result['http_code'] ?? '?') . ') : ' . substr($result['body'], 0, 120);
            continue;
        }

        if (isset($parsed['message']) && !isset($parsed['response'])) {
            $msg = $parsed['message'];
            if (stripos($msg, 'too many requests') !== false || stripos($msg, 'rate limit') !== false) {
                $msg .= ' — Attends quelques minutes ou utilise la clé directe (dashboard.api-football.com).';
            }
            if (stripos($msg, 'application key') !== false || stripos($msg, 'missing') !== false) {
                $msg .= ' — Utilise la clé du dashboard api-football.com pour "direct", ou la clé RapidAPI pour "RapidAPI" (voir football_data_config.php).';
            }
            $debugLog[] = $ep['label'] . ' → ' . $msg;
            continue;
        }

        if (isset($parsed['errors']) && !empty($parsed['errors'])) {
            $errMsg = is_array($parsed['errors']) ? implode(', ', $parsed['errors']) : (string)$parsed['errors'];
            $debugLog[] = $ep['label'] . ' → ' . $errMsg;
            continue;
        }

        if (isset($parsed['response']) && is_array($parsed['response'])) {
            $data = $parsed;
            $usedLabel = $ep['label'];
            break;
        }

        $debugLog[] = $ep['label'] . ' → Réponse inattendue : ' . substr($result['body'], 0, 200);
    }

    if ($data === null) {
        $detail = implode(' | ', $debugLog);
        return ['inserted' => 0, 'total' => 0, 'source' => 'API-Football', 'error' => 'API-Football : aucun endpoint n\'a répondu. Détails : ' . $detail];
    }

    $fixtures = $data['response'];
    $allowedIdsLookup = [];
    if (!empty($allowedLeagueIds)) {
        $allowedIdsLookup = array_fill_keys(array_map('intval', $allowedLeagueIds), true);
    }

    $stmtExists = $db->prepare("SELECT 1 FROM commu_matches WHERE match_date = ? AND team_home = ? AND team_away = ?");
    $stmtIns = $db->prepare("INSERT INTO commu_matches (match_date, team_home, team_away, competition, heure, vote_closed_at) VALUES (?, ?, ?, ?, ?, ?)");
    $inserted = 0;
    $afterFilter = 0;

    foreach ($fixtures as $fx) {
        $leagueId = isset($fx['league']['id']) ? (int) $fx['league']['id'] : 0;
        if (!empty($allowedIdsLookup) && !isset($allowedIdsLookup[$leagueId])) {
            continue;
        }
        $afterFilter++;

        $leagueName = trim($fx['league']['name'] ?? '');

        $home = trim($fx['teams']['home']['name'] ?? '');
        $away = trim($fx['teams']['away']['name'] ?? '');
        if ($home === '' || $away === '') continue;

        $status = $fx['fixture']['status']['short'] ?? '';
        if (in_array($status, ['FT', 'AET', 'PEN', 'CANC', 'ABD', 'AWD', 'WO', 'PST'], true)) continue;

        $competition = $leagueName;
        $country = trim($fx['league']['country'] ?? '');
        if ($country !== '' && $competition !== '') {
            $competition = $country . ' — ' . $competition;
        }

        $heure = '';
        $fixtureDate = $fx['fixture']['date'] ?? '';
        if ($fixtureDate !== '') {
            try {
                $dt = new DateTime($fixtureDate);
                $dt->setTimezone($tzParis);
                $heure = $dt->format('H:i');
            } catch (Exception $e) { }
        }

        $stmtExists->execute([$targetDate, $home, $away]);
        if ($stmtExists->fetch()) continue;

        $stmtIns->execute([$targetDate, $home, $away, $competition ?: null, $heure ?: null, $voteClosedAt]);
        $inserted++;
    }

    $totalApi = count($fixtures);
    return [
        'inserted' => $inserted,
        'total' => $afterFilter,
        'total_api' => $totalApi,
        'source' => 'API-Football (' . $usedLabel . ')',
        'error' => '',
    ];
}

// ─────────────────────────────────────────────────────────────
// Football-Data.org (free tier — ~12 compétitions)
// ─────────────────────────────────────────────────────────────
function importFromFootballData(PDO $db, string $targetDate, string $voteClosedAt, DateTimeZone $tzParis, string $apiKey): array
{
    $dayAfter = (new DateTime($targetDate))->modify('+1 day')->format('Y-m-d');
    $url = 'https://api.football-data.org/v4/matches?dateFrom=' . $targetDate . '&dateTo=' . $dayAfter;
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  => "X-Auth-Token: " . $apiKey . "\r\n",
            'timeout' => 15,
            'ignore_errors' => true,
        ]
    ]);

    $json = @file_get_contents($url, false, $ctx);
    if ($json === false) {
        return ['inserted' => 0, 'total' => 0, 'source' => 'Football-Data.org', 'error' => 'Impossible de contacter l\'API Football-Data (timeout / réseau).'];
    }

    $data = @json_decode($json, true);
    if (!isset($data['matches']) || !is_array($data['matches'])) {
        $msg = $data['message'] ?? $data['error'] ?? 'Réponse API invalide.';
        return ['inserted' => 0, 'total' => 0, 'source' => 'Football-Data.org', 'error' => 'Football-Data.org : ' . $msg];
    }

    $stmtExists = $db->prepare("SELECT 1 FROM commu_matches WHERE match_date = ? AND team_home = ? AND team_away = ?");
    $stmtIns = $db->prepare("INSERT INTO commu_matches (match_date, team_home, team_away, competition, heure, vote_closed_at) VALUES (?, ?, ?, ?, ?, ?)");
    $inserted = 0;

    foreach ($data['matches'] as $m) {
        $home = trim($m['homeTeam']['name'] ?? $m['homeTeam']['shortName'] ?? '');
        $away = trim($m['awayTeam']['name'] ?? $m['awayTeam']['shortName'] ?? '');
        if ($home === '' || $away === '') continue;

        $competition = trim($m['competition']['name'] ?? '');
        $heure = '';
        if (!empty($m['utcDate'])) {
            try {
                $dt = new DateTime($m['utcDate'], new DateTimeZone('UTC'));
                $dt->setTimezone($tzParis);
                $heure = $dt->format('H:i');
            } catch (Exception $e) { }
        }

        $stmtExists->execute([$targetDate, $home, $away]);
        if ($stmtExists->fetch()) continue;

        $stmtIns->execute([$targetDate, $home, $away, $competition ?: null, $heure ?: null, $voteClosedAt]);
        $inserted++;
    }

    return ['inserted' => $inserted, 'total' => count($data['matches']), 'source' => 'Football-Data.org', 'error' => ''];
}
