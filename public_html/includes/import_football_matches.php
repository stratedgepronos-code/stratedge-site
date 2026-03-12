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

    $apiFootballKey = trim($config['api_football_key'] ?? '');
    $footballDataKey = trim($config['api_key'] ?? '');

    if ($apiFootballKey !== '') {
        return importFromApiFootball($db, $targetDate, $voteClosedAt, $tzParis, $apiFootballKey);
    }

    if ($footballDataKey !== '') {
        return importFromFootballData($db, $targetDate, $voteClosedAt, $tzParis, $footballDataKey);
    }

    return ['inserted' => 0, 'total' => 0, 'source' => '', 'error' => 'Aucune clé API configurée. Ajoute ta clé dans includes/football_data_config.php.'];
}

// ─────────────────────────────────────────────────────────────
// API-Football (api-sports.io) — toutes compétitions
// ─────────────────────────────────────────────────────────────
function importFromApiFootball(PDO $db, string $targetDate, string $voteClosedAt, DateTimeZone $tzParis, string $apiKey): array
{
    $url = 'https://v3.football.api-sports.io/fixtures?date=' . $targetDate;
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  => "x-apisports-key: " . $apiKey . "\r\n",
            'timeout' => 20,
            'ignore_errors' => true,
        ]
    ]);

    $json = @file_get_contents($url, false, $ctx);
    if ($json === false) {
        return ['inserted' => 0, 'total' => 0, 'source' => 'API-Football', 'error' => 'Impossible de contacter API-Football (timeout / réseau).'];
    }

    $data = @json_decode($json, true);

    if (isset($data['errors']) && !empty($data['errors'])) {
        $errMsg = is_array($data['errors']) ? implode(', ', $data['errors']) : (string)$data['errors'];
        return ['inserted' => 0, 'total' => 0, 'source' => 'API-Football', 'error' => 'API-Football erreur : ' . $errMsg];
    }

    if (!isset($data['response']) || !is_array($data['response'])) {
        return ['inserted' => 0, 'total' => 0, 'source' => 'API-Football', 'error' => 'Réponse API-Football invalide (pas de "response").'];
    }

    $fixtures = $data['response'];
    $stmtExists = $db->prepare("SELECT 1 FROM commu_matches WHERE match_date = ? AND team_home = ? AND team_away = ?");
    $stmtIns = $db->prepare("INSERT INTO commu_matches (match_date, team_home, team_away, competition, heure, vote_closed_at) VALUES (?, ?, ?, ?, ?, ?)");
    $inserted = 0;

    foreach ($fixtures as $fx) {
        $home = trim($fx['teams']['home']['name'] ?? '');
        $away = trim($fx['teams']['away']['name'] ?? '');
        if ($home === '' || $away === '') continue;

        $status = $fx['fixture']['status']['short'] ?? '';
        if (in_array($status, ['FT', 'AET', 'PEN', 'CANC', 'ABD', 'AWD', 'WO', 'PST'], true)) continue;

        $competition = trim($fx['league']['name'] ?? '');
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

    return ['inserted' => $inserted, 'total' => count($fixtures), 'source' => 'API-Football', 'error' => ''];
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
