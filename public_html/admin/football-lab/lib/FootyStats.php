<?php
declare(strict_types=1);
namespace StratEdgeLab;
require_once __DIR__ . '/HistoricalContext.php';

/** Server-side enrichment. Provider failures never turn missing statistics into zero. */
final class FootyStats
{
    // Verified against the supplied PackBall export and the provider's fixture IDs.
    // Country scopes prevent short names such as Inter from matching another club abroad.
    private const PACKBALL_TEAM_IDS = [
        // Verified September 17 fixture identities; both sides and kickoff still checked.
        'Canada' => ['CF Montréal' => 1],
        'Europe' => ['TSG Hoffenheim' => 49, 'Ferencvárosi' => 119, 'NEC Nijmegen' => 379],
        'Romania' => ['Otelul' => 6633],
        'Norway' => ['Bodø / Glimt' => 332],
        'Denmark' => ['FC Midtjylland' => 955, 'Brøndby IF' => 2517],
        'Turkey' => ['Gaziantep F.K.' => 355],
        'Spain' => ['Celta Fortuna' => 4515],
        'Republic of Ireland' => ["St Patrick's" => 981, 'Waterford United' => 2064],
        'Italy' => ['Inter' => 470],
        'Portugal' => ['Marítimo' => 177, 'Estoril' => 164],
    ];
    private $key;
    private $transport;
    private $store;
    private $aliases;
    private $memo = [];
    private $calls = 0;
    private $deadline;

    public function __construct(string $key, ?callable $transport = null, ?Store $store = null, array $aliases = [])
    {
        $this->key = $key;
        $this->transport = $transport;
        $this->store = $store;
        $this->aliases = $aliases;
        $this->deadline = microtime(true) + 45;
    }

    public static function configured(?Store $store = null): self
    {
        $root = dirname(__DIR__, 3);
        if (is_readable($root . '/config-keys.php')) { require_once $root . '/config-keys.php'; }
        $local = is_readable(dirname(__DIR__) . '/config.local.php') ? require dirname(__DIR__) . '/config.local.php' : [];
        $local = is_array($local) ? $local : [];
        $key = getenv('STRATEDGE_LAB_FOOTYSTATS_KEY') ?: getenv('FOOTYSTATS_API_KEY') ?: ($local['footystats_api_key'] ?? '');
        if ($key === '' && defined('FOOTYSTATS_API_KEY')) { $key = (string)constant('FOOTYSTATS_API_KEY'); }
        return new self(trim((string)$key), null, $store, $local['footystats_team_aliases'] ?? []);
    }

    public function ready(): bool { return $this->key !== '' && $this->key !== 'REPLACE-ME'; }

    /** Keep only consumed fixture fields: full season payloads otherwise accumulate in PHP memory. */
    private static function compact(string $endpoint, array $response): array
    {
        if (!in_array($endpoint, ['league-matches', 'todays-matches'], true)) { return $response; }
        $fields = array_flip(['id', 'competition_id', 'homeID', 'awayID', 'date_unix', 'status',
            'no_home_away', 'homeGoalCount', 'awayGoalCount', 'home_name', 'away_name', 'season']);
        $rows = [];
        foreach ($response['data'] as $row) {
            if (!is_array($row)) { throw new \RuntimeException('Liste FootyStats invalide.'); }
            $rows[] = array_intersect_key($row, $fields);
        }
        return ['data' => $rows, 'pager' => $response['pager'] ?? []];
    }

    private function request(string $endpoint, array $params): array
    {
        if (!$this->ready()) { throw new \RuntimeException('Clé FootyStats absente de la configuration serveur.'); }
        $cacheKey = hash('sha256', $this->key . '|' . $endpoint . '|' . json_encode($params));
        if (isset($this->memo[$cacheKey])) { return $this->memo[$cacheKey]; }
        if ($this->store) {
            $cached = $this->store->cacheGet($cacheKey);
            if ($cached !== null) { return $this->memo[$cacheKey] = self::compact($endpoint, $cached); }
        }
        if (++$this->calls > 40 || microtime(true) >= $this->deadline) { throw new \RuntimeException('Enrichissement partiel : délai ou limite d’appels atteint. Réimporte le fichier pour compléter les données grâce au cache.'); }
        if ($this->transport) {
            // The injected test transport never receives the secret.
            try { $response = ($this->transport)($endpoint, $params); }
            catch (\Throwable $e) { throw new \RuntimeException('FootyStats indisponible.'); }
        } else {
            if (!function_exists('curl_init')) { throw new \RuntimeException('Extension PHP cURL indisponible.'); }
            $url = 'https://api.football-data-api.com/' . $endpoint . '?' . http_build_query(['key' => $this->key] + $params, '', '&', PHP_QUERY_RFC3986);
            $ch = curl_init($url);
            $raw = '';
            curl_setopt_array($ch, [CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT_MS => max(1, min(10000, (int)(($this->deadline - microtime(true)) * 1000))),
                CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_WRITEFUNCTION => static function ($handle, string $bytes) use (&$raw): int {
                    if (strlen($raw) + strlen($bytes) > 8388608) { return 0; }
                    $raw .= $bytes; return strlen($bytes);
                }]);
            $ok = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            if ($status === 401 || $status === 403) { throw new \RuntimeException('Clé FootyStats refusée ou accès à cette compétition non autorisé.'); }
            if ($status === 429) { throw new \RuntimeException('Quota FootyStats atteint. Réessaie après son renouvellement.'); }
            if (!$ok || $status !== 200) { throw new \RuntimeException('FootyStats indisponible : connexion ou réponse HTTP invalide.'); }
            $response = json_decode($raw, true);
            unset($raw);
        }
        if (!is_array($response) || ($response['success'] ?? null) !== true || !is_array($response['data'] ?? null)) {
            throw new \RuntimeException('Réponse FootyStats invalide ou accès non autorisé.');
        }
        // Never persist provider messages, request URLs, quota details or credentials.
        $safe = self::compact($endpoint, ['data' => $response['data'], 'pager' => $response['pager'] ?? []]);
        unset($response);
        if ($this->store) { $this->store->cachePut($cacheKey, $safe, time() + 900); }
        return $this->memo[$cacheKey] = $safe;
    }

    private function pages(string $endpoint, array $params): array
    {
        $rows = [];
        for ($page = 1; $page <= 20; $page++) {
            $response = $this->request($endpoint, $params + ['page' => $page]);
            foreach ($response['data'] as $row) { if (!is_array($row)) { throw new \RuntimeException('Liste FootyStats invalide.'); } $rows[] = $row; }
            $pager = $response['pager'];
            $last = isset($pager['max_page']) && is_numeric($pager['max_page']) ? (int)$pager['max_page'] : null;
            if ($last !== null && $page >= $last) { return $rows; }
            if (!$response['data']) { return $rows; }
            // Without paging metadata an extra empty page is required; never assume completeness.
        }
        throw new \RuntimeException('Pagination FootyStats incomplète : aucune statistique de ligue utilisée.');
    }

    public function checkConnection(): array
    {
        $response = $this->request('league-list', ['chosen_leagues_only' => 'true']);
        return ['checked_at' => gmdate('c'), 'leagues' => count($response['data'])];
    }

    public static function normalizedName(string $name): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $name = strtolower($ascii === false ? $name : $ascii);
        $name = trim(preg_replace('/[^a-z0-9]+/', ' ', $name));
        // Legal club suffix only. Preserve cities, gender, age and reserve-team identifiers.
        return trim(preg_replace('/\s+(fc|cf|afc)$/', '', $name));
    }

    private function name(string $name): string
    {
        return self::normalizedName((string)($this->aliases[$name] ?? $name));
    }

    private function teamMatches(array $match, array $fixture, string $side): bool
    {
        if ($this->name((string)($fixture[$side . '_name'] ?? '')) === $this->name($match[$side])) { return true; }
        $expected = self::PACKBALL_TEAM_IDS[$match['country'] ?? ''][$match[$side]] ?? null;
        return $expected !== null && (int)($fixture[$side . 'ID'] ?? 0) === $expected;
    }

    public function findFixture(array $match, array $fixtures): array
    {
        $found = [];
        foreach ($fixtures as $fixture) {
            if (!is_numeric($fixture['date_unix'] ?? null) || abs((int)$fixture['date_unix'] - strtotime($match['kickoff'])) > 900) { continue; }
            if (!$this->teamMatches($match, $fixture, 'home') || !$this->teamMatches($match, $fixture, 'away')) { continue; }
            foreach (['id', 'homeID', 'awayID', 'competition_id'] as $field) {
                if (filter_var($fixture[$field] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                    throw new \RuntimeException('Identifiants FootyStats incomplets.');
                }
            }
            if (isset($found[(int)$fixture['id']]) && $found[(int)$fixture['id']] !== $fixture) { throw new \RuntimeException('Fiches FootyStats contradictoires pour le même match.'); }
            $found[(int)$fixture['id']] = $fixture;
        }
        if (count($found) !== 1) { throw new \RuntimeException(count($found) ? 'Correspondance FootyStats ambiguë : contrôle des équipes nécessaire.' : 'Match absent des correspondances FootyStats : vérifier la couverture de la compétition, les noms et l’horaire.'); }
        $fixture = reset($found);
        if (!in_array(strtolower((string)($fixture['status'] ?? '')), ['incomplete', 'not started', 'scheduled', 'ns'], true)
            || (int)$fixture['date_unix'] <= time()) { throw new \RuntimeException('FootyStats indique un match commencé, terminé ou reporté.'); }
        return $fixture;
    }

    private static function number(array $stats, string $key, float $max = 10000): ?float
    {
        $v = $stats[$key] ?? null;
        return is_numeric($v) && is_finite((float)$v) && $v >= 0 && $v <= $max ? (float)$v : null;
    }

    public static function split(array $team, string $venue): array
    {
        if (!in_array($venue, ['home', 'away', 'overall'], true)) { throw new \InvalidArgumentException('Lieu invalide.'); }
        $s = $team['stats'] ?? [];
        if (!is_array($s)) { throw new \RuntimeException('Statistiques FootyStats absentes.'); }
        $n = self::number($s, 'seasonMatchesPlayed_' . $venue);
        $total = self::number($s, 'seasonGoalsTotal_' . $venue);
        $against = self::number($s, 'seasonConcededNum_' . $venue);
        // GoalsTotal means scored PLUS conceded, confirmed against real provider samples.
        if ($n === null || $n < 1 || floor($n) !== $n || $total === null || $against === null || $total < $against
            || floor($total) !== $total || floor($against) !== $against) { throw new \RuntimeException('Échantillon FootyStats vide ou totaux de buts incomplets.'); }
        $gf = ($total - $against) / $n; $ga = $against / $n;
        if ($gf > 20 || $ga > 20) { throw new \RuntimeException('Moyennes FootyStats incohérentes.'); }
        $out = ['n' => (int)$n, 'gf' => $gf, 'ga' => $ga, 'goals_total' => (int)$total];
        foreach (['cs' => 'seasonCSPercentage', 'fts' => 'seasonFTSPercentage', 'btts' => 'seasonBTTSPercentage',
            'shots' => 'shotsAVG', 'sot' => 'shotsOnTargetAVG', 'possession' => 'possessionAVG', 'ppg' => 'seasonPPG',
            'xg_for' => 'xg_for_avg', 'xg_against' => 'xg_against_avg'] as $key => $field) {
            $out[$key] = self::number($s, $field . '_' . $venue, in_array($key, ['ppg'], true) ? 3 : 100);
        }
        foreach (['05', '15', '25', '35', '45'] as $line) { $out['over' . $line] = self::number($s, 'seasonOver' . $line . 'Percentage_' . $venue, 100); }
        return $out;
    }

    /** Exact league names only; unrecognized domestic competitions stay unresolved. */
    private const DOMESTIC_LEAGUES = [
        'Brazil' => ['Serie A'], 'Argentina' => ['Primera División', 'Primera Division'],
        'Ecuador' => ['Primera Categoría Serie A'], 'Colombia' => ['Categoria Primera A'],
        'Greece' => ['Super League'], 'Poland' => ['Ekstraklasa'], 'Bulgaria' => ['First League'],
        'England' => ['Premier League'], 'Spain' => ['La Liga'], 'France' => ['Ligue 1'],
        'Germany' => ['Bundesliga'], 'Italy' => ['Serie A'], 'Portugal' => ['Liga NOS', 'Primeira Liga'],
        'Belgium' => ['Pro League', 'First Division A'], 'Czech Republic' => ['First League'],
        'Czechia' => ['First League'], 'Netherlands' => ['Eredivisie'], 'Scotland' => ['Premiership'],
        'Austria' => ['Bundesliga'], 'Switzerland' => ['Super League'], 'Denmark' => ['Superliga'],
        'Norway' => ['Eliteserien'], 'Sweden' => ['Allsvenskan'], 'Turkey' => ['Süper Lig', 'Super Lig'],
    ];

    private function history(array $match, int $cutoff): array
    {
        $out = ['version' => HistoricalContext::VERSION, 'status' => 'unavailable', 'sources' => [], 'issues' => [],
            'message' => 'Historique complémentaire descriptif uniquement ; aucune fusion entre compétitions ou saisons.'];
        $fs = $match['footystats'];
        if (empty($fs['home_id']) || empty($fs['away_id'])) {
            $out['issues'][] = 'Identités API non établies : aucun historique associé automatiquement.';
            return $out;
        }
        try { $catalog = $this->request('league-list', ['chosen_leagues_only' => 'true'])['data']; }
        catch (\Throwable $e) { $out['issues'][] = 'Catalogue des compétitions indisponible.'; return $out; }
        foreach (['home', 'away'] as $venue) {
            try {
                $teamId = (int)$fs[$venue . '_id']; $candidateLeagues = [];
                foreach ($catalog as $league) {
                    foreach ($league['season'] ?? [] as $season) {
                        if ((int)($season['id'] ?? 0) === (int)$fs['season_id']) { $candidateLeagues[] = $league; break; }
                    }
                }
                // Discover the team's country by its verified provider ID, never by a fuzzy name.
                try { $teams = !empty($fs['team_countries'][$venue]) ? [['id' => $teamId, 'country' => $fs['team_countries'][$venue]]] : $this->request('team', ['team_id' => $teamId])['data']; }
                catch (\Throwable $e) { $teams = []; $out['issues'][] = $venue . ' : pays du club non confirmé ; championnat non associé.'; }
                $countries = []; $teamSeasons = [];
                foreach ($teams as $team) {
                    if ((int)($team['id'] ?? 0) === $teamId && !empty($team['country'])) { $countries[(string)$team['country']] = true; if (!empty($team['competition_id'])) { $teamSeasons[(int)$team['competition_id']] = true; } }
                }
                if (count($countries) === 1) {
                    $country = (string)key($countries); $domesticFound = false;
                    foreach ($catalog as $league) {
                        $leagueName = $league['league_name'] ?? '';
                        if ($leagueName === '' && strpos($league['name'] ?? '', $country . ' ') === 0) { $leagueName = substr($league['name'], strlen($country) + 1); }
                        if (($league['country'] ?? '') === $country && in_array($leagueName, self::DOMESTIC_LEAGUES[$country] ?? [], true)) { $candidateLeagues[] = $league; $domesticFound = true; }
                    }
                    if (!$domesticFound) { $out['issues'][] = $venue . ' : championnat de ' . $country . ' non identifié dans les compétitions accessibles.'; }
                }
                $seen = []; $sourceCount = 0;
                // The current competition's previous season and the domestic current/previous seasons
                // remain separate records. Membership is established from actual historical fixtures.
                foreach ($candidateLeagues as $league) {
                    $seasons = array_filter($league['season'] ?? [], static function ($season) use ($cutoff) {
                        $year = (int)substr((string)($season['year'] ?? ''), 0, 4);
                        return $year >= (int)gmdate('Y', $cutoff) - 1 && $year <= (int)gmdate('Y', $cutoff) && !empty($season['id']);
                    });
                    usort($seasons, static function ($a, $b) { return strcmp((string)$b['year'], (string)$a['year']); });
                    foreach (array_slice($seasons, 0, 2) as $season) {
                        $id = (int)$season['id'];
                        if ($id === (int)$fs['season_id'] || isset($seen[$id]) || ($teamSeasons && !isset($teamSeasons[$id]))) { continue; }
                        $seen[$id] = true;
                        $rows = $this->pages('league-matches', ['season_id' => $id, 'max_time' => $cutoff, 'max_per_page' => 1000]);
                        $history = HistoricalContext::summarize($rows, $teamId, $venue, $id, $cutoff);
                        $history['competition'] = $league['name'] ?? $league['league_name'] ?? ('Saison ' . $id);
                        $history['season'] = $season['year']; $history['provider'] = 'FootyStats'; $history['endpoint'] = 'league-matches';
                        $out['sources'][$venue][] = $history; $sourceCount++;
                    }
                }
                if (!$sourceCount) { $out['issues'][] = $venue . ' : aucune saison alternative identifiée dans les compétitions accessibles.'; }
            } catch (\Throwable $e) {
                // Never include raw upstream/SQL errors; partial successful sources remain visible.
                $reason = $e instanceof \PDOException ? 'Cache FootyStats indisponible.' : ($e instanceof \RuntimeException ? $e->getMessage() : 'Réponse historique inexploitable.');
                $out['issues'][] = $venue . ' : ' . $reason;
            }
        }
        foreach ($out['sources'] as $sources) {
            foreach ($sources as $source) { if ($source['n'] > 0) { $out['status'] = 'descriptive_only'; } }
        }
        return $out;
    }

    public function enrich(array $analysis): array
    {
        $summary = ['enriched' => 0, 'unavailable' => 0, 'configured' => $this->ready(), 'checked_at' => gmdate('c')];
        // Immutable cutoff, rounded down for shared caching. Never request post-kickoff statistics.
        $cutoff = (int)(floor(time() / 900) * 900);
        $leagueCache = [];
        foreach ($analysis['matches'] as &$match) {
            $match['packball_stats'] = $match['stats'];
            $evidence = ['status' => 'unavailable', 'as_of' => gmdate('c', $cutoff), 'sample' => ['home_n' => null, 'away_n' => null]];
            $stage = 'api_unavailable';
            try {
                $date = (new \DateTimeImmutable($match['kickoff']))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d');
                $fixtures = $this->pages('todays-matches', ['date' => $date, 'timezone' => 'Etc/UTC']);
                $stage = 'api_match_missing';
                $fixture = $this->findFixture($match, $fixtures);
                $season = (int)$fixture['competition_id'];
                $evidence += ['match_id' => (int)$fixture['id'], 'season_id' => $season,
                    'home_id' => (int)$fixture['homeID'], 'away_id' => (int)$fixture['awayID'],
                    'source' => ['provider' => 'FootyStats', 'endpoint' => 'league-teams', 'season_id' => $season,
                        'competition' => $match['league'], 'season' => $fixture['season'] ?? null, 'period' => 'Saison de cette compétition ; dates des matchs non fournies par cet agrégat',
                        'period_start' => null, 'as_of' => gmdate('c', $cutoff), 'scope' => 'home_for_host_away_for_visitor']];
                $stage = 'api_unavailable';
                if (!isset($leagueCache[$season])) {
                    $teams = $this->pages('league-teams', ['season_id' => $season, 'include' => 'stats', 'max_time' => $cutoff]);
                    $stage = 'data_incomplete';
                    $byId = []; $goals = 0; $played = 0; $complete = true;
                    foreach ($teams as $team) {
                        // /league-teams is already scoped by season_id. Production responses
                        // may omit competition_id even though the documentation example has it.
                        if ((isset($team['competition_id']) && (int)$team['competition_id'] !== $season)
                            || empty($team['id']) || isset($byId[(int)$team['id']])) { throw new \RuntimeException('Saison ou équipes FootyStats incohérentes.'); }
                        $byId[(int)$team['id']] = $team;
                        $s = $team['stats'] ?? [];
                        if (!is_array($s)) { throw new \RuntimeException('Statistiques de ligue FootyStats invalides.'); }
                        $n = self::number($s, 'seasonMatchesPlayed_overall');
                        $total = self::number($s, 'seasonGoalsTotal_overall');
                        if ($n === null || $total === null || floor($n) !== $n || floor($total) !== $total || ($n === 0.0 && $total > 0)) { $complete = false; continue; }
                        $played += $n; $goals += $total;
                    }
                    $leagueCache[$season] = ['teams' => $byId, 'average' => $complete && $played > 0 ? $goals / $played : null];
                }
                $league = $leagueCache[$season];
                $stage = 'data_incomplete';
                foreach (['home', 'away'] as $side) {
                    $teamRow = $league['teams'][(int)$fixture[$side . 'ID']] ?? [];
                    $evidence['team_countries'][$side] = $teamRow['country'] ?? null;
                    $raw = $teamRow['stats'] ?? [];
                    $n = is_array($raw) ? self::number($raw, 'seasonMatchesPlayed_' . $side) : null;
                    $evidence['sample'][$side . '_n'] = $n !== null && floor($n) === $n ? (int)$n : null;
                }
                if ($evidence['sample']['home_n'] === 0 || $evidence['sample']['away_n'] === 0) {
                    $stage = 'sample_insufficient';
                    throw new \RuntimeException('Échantillon FootyStats nul dans la compétition du match : domicile ' . ($evidence['sample']['home_n'] ?? 'inconnu') . ', extérieur ' . ($evidence['sample']['away_n'] ?? 'inconnu') . '.');
                }
                $home = self::split($league['teams'][(int)$fixture['homeID']] ?? [], 'home');
                $away = self::split($league['teams'][(int)$fixture['awayID']] ?? [], 'away');
                foreach (['home' => $home, 'away' => $away] as $side => $stats) {
                    foreach (['n', 'gf', 'ga'] as $field) { $match['stats'][$side . '_' . $field] = $stats[$field]; }
                }
                $match['warnings'] = array_values(array_filter($match['warnings'], static function ($warning) { return !preg_match('/^(Domicile|Extérieur) : échantillon limité/', $warning); }));
                foreach (['home' => 'à domicile', 'away' => 'à l’extérieur'] as $side => $label) {
                    if ($match['stats'][$side . '_n'] < 20) { $match['warnings'][] = 'FootyStats ' . $label . ' : échantillon limité à ' . $match['stats'][$side . '_n'] . ' matchs.'; }
                }
                $match['footystats'] = ['source' => $evidence['source'], 'team_countries' => $evidence['team_countries'], 'sample' => $evidence['sample'], 'status' => 'enriched', 'match_id' => (int)$fixture['id'], 'season_id' => $season,
                    'home_id' => (int)$fixture['homeID'], 'away_id' => (int)$fixture['awayID'], 'as_of' => gmdate('c', $cutoff),
                    'home' => $home, 'away' => $away, 'league_average' => $league['average'], 'message' => 'Bilans de saison : domicile pour le recevant, extérieur pour le visiteur.'];
                $summary['enriched']++;
            } catch (\PDOException $e) {
                $match['footystats'] = array_merge($evidence, ['failure_code' => 'api_unavailable', 'status' => 'unavailable', 'message' => 'Cache FootyStats indisponible. Vérifie le stockage du Lab.']);
                $summary['unavailable']++;
            } catch (\RuntimeException $e) {
                $match['footystats'] = array_merge($evidence, ['failure_code' => $stage, 'status' => 'unavailable', 'message' => $e->getMessage()]);
                $summary['unavailable']++;
            } catch (\Throwable $e) {
                $match['footystats'] = array_merge($evidence, ['failure_code' => 'data_incomplete', 'status' => 'unavailable', 'message' => 'Réponse FootyStats inexploitable. Aucun enrichissement utilisé.']);
                $summary['unavailable']++;
            }
        }
        unset($match);
        // Complete primary enrichment first so optional histories cannot starve later fixtures.
        foreach ($analysis['matches'] as &$match) {
            $fs = $match['footystats'];
            if ($fs['status'] !== 'enriched' || min($fs['home']['n'], $fs['away']['n']) < 8) {
                $match['historical_context'] = $this->history($match, $cutoff);
            }
        }
        unset($match);
        $analysis['footystats'] = $summary;
        return $analysis;
    }
}
