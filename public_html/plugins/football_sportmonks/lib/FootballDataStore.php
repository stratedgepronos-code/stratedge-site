<?php
declare(strict_types=1);

/**
 * Persistance des entités SportMonks en base fd_sm_*.
 */
final class FootballDataStore
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    /**
     * @param array<string,mixed> $item Réponse fixture « enrichie » (includes)
     */
    public function upsertFixtureTree(array $item, bool $storeDetailPayload = true): void
    {
        $smId = (int) ($item['id'] ?? 0);
        if ($smId <= 0) {
            return;
        }

        if (isset($item['league']) && is_array($item['league'])) {
            $this->upsertLeague($item['league']);
        }
        if (isset($item['season']) && is_array($item['season'])) {
            $this->upsertSeasonAsMeta($item['season']);
        }
        if (isset($item['venue']) && is_array($item['venue'])) {
            $this->upsertVenue($item['venue']);
        }

        $homeId = null;
        $awayId = null;
        $participants = $item['participants'] ?? [];
        if (is_array($participants)) {
            foreach ($participants as $p) {
                if (!is_array($p)) {
                    continue;
                }
                $this->upsertTeam($p);
                $tid = (int) ($p['id'] ?? 0);
                if ($tid <= 0) {
                    continue;
                }
                $loc = $this->participantLocation($p);
                if ($loc === 'home') {
                    $homeId = $tid;
                } elseif ($loc === 'away') {
                    $awayId = $tid;
                }
            }
            if ($homeId === null || $awayId === null) {
                $ids = [];
                foreach ($participants as $p) {
                    if (is_array($p) && isset($p['id'])) {
                        $ids[] = (int) $p['id'];
                    }
                }
                if (count($ids) >= 2) {
                    $homeId = $homeId ?? $ids[0];
                    $awayId = $awayId ?? $ids[1];
                }
            }
        }

        $refereeId = null;
        $refs = $item['referees'] ?? [];
        if (is_array($refs)) {
            foreach ($refs as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $this->upsertReferee($r);
                if ($refereeId === null && isset($r['id'])) {
                    $refereeId = (int) $r['id'];
                }
            }
        }

        $leagueSmId = isset($item['league_id']) ? (int) $item['league_id'] : null;
        $seasonSmId = isset($item['season_id']) ? (int) $item['season_id'] : null;
        $venueSmId = isset($item['venue_id']) ? (int) $item['venue_id'] : null;
        $stateId = isset($item['state_id']) ? (int) $item['state_id'] : null;

        $startingAt = null;
        if (!empty($item['starting_at']) && is_string($item['starting_at'])) {
            $startingAt = substr($item['starting_at'], 0, 19);
        }

        $scores = $item['scores'] ?? null;
        $scoresJson = is_array($scores) ? json_encode($scores, JSON_UNESCAPED_UNICODE) : null;

        $summary = [
            'leg' => $item['leg'] ?? null,
            'length' => $item['length'] ?? null,
            'placeholder' => $item['placeholder'] ?? null,
        ];

        $sql = 'INSERT INTO fd_sm_fixture (
            sm_id, league_sm_id, season_sm_id, venue_sm_id, state_id, starting_at,
            name, result_info, home_team_sm_id, away_team_sm_id, referee_sm_id,
            scores_json, summary_json
        ) VALUES (
            :sm_id, :league_sm_id, :season_sm_id, :venue_sm_id, :state_id, :starting_at,
            :name, :result_info, :home_team_sm_id, :away_team_sm_id, :referee_sm_id,
            :scores_json, :summary_json
        ) ON DUPLICATE KEY UPDATE
            league_sm_id = VALUES(league_sm_id),
            season_sm_id = VALUES(season_sm_id),
            venue_sm_id = VALUES(venue_sm_id),
            state_id = VALUES(state_id),
            starting_at = VALUES(starting_at),
            name = VALUES(name),
            result_info = VALUES(result_info),
            home_team_sm_id = VALUES(home_team_sm_id),
            away_team_sm_id = VALUES(away_team_sm_id),
            referee_sm_id = VALUES(referee_sm_id),
            scores_json = VALUES(scores_json),
            summary_json = VALUES(summary_json),
            updated_at = CURRENT_TIMESTAMP';

        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':sm_id' => $smId,
            ':league_sm_id' => $leagueSmId,
            ':season_sm_id' => $seasonSmId,
            ':venue_sm_id' => $venueSmId ?: null,
            ':state_id' => $stateId,
            ':starting_at' => $startingAt,
            ':name' => isset($item['name']) ? (string) $item['name'] : null,
            ':result_info' => isset($item['result_info']) ? (string) $item['result_info'] : null,
            ':home_team_sm_id' => $homeId,
            ':away_team_sm_id' => $awayId,
            ':referee_sm_id' => $refereeId,
            ':scores_json' => $scoresJson,
            ':summary_json' => json_encode($summary, JSON_UNESCAPED_UNICODE),
        ]);

        if ($storeDetailPayload) {
            $payload = json_encode($item, JSON_UNESCAPED_UNICODE);
            if ($payload !== false) {
                $st2 = $this->pdo->prepare(
                    'INSERT INTO fd_sm_fixture_detail (fixture_sm_id, payload) VALUES (:id, :payload)
                     ON DUPLICATE KEY UPDATE payload = VALUES(payload), fetched_at = CURRENT_TIMESTAMP'
                );
                $st2->execute([':id' => $smId, ':payload' => $payload]);
            }
        }
    }

    /**
     * @param array<string,mixed> $league
     */
    public function upsertLeague(array $league): void
    {
        $id = (int) ($league['id'] ?? 0);
        if ($id <= 0) {
            return;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO fd_sm_league (sm_id, name, country_id, raw_json) VALUES (:id, :name, :cid, :raw)
             ON DUPLICATE KEY UPDATE name = VALUES(name), country_id = VALUES(country_id), raw_json = VALUES(raw_json), updated_at = CURRENT_TIMESTAMP'
        );
        $st->execute([
            ':id' => $id,
            ':name' => isset($league['name']) ? (string) $league['name'] : null,
            ':cid' => isset($league['country_id']) ? (int) $league['country_id'] : null,
            ':raw' => json_encode($league, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * La saison est stockée via season_id sur le fixture ; on enrichit summary si besoin plus tard.
     *
     * @param array<string,mixed> $season
     */
    public function upsertSeasonAsMeta(array $season): void
    {
        // Réservé : table fd_sm_season si besoin V2
    }

    /**
     * @param array<string,mixed> $team
     */
    public function upsertTeam(array $team): void
    {
        $id = (int) ($team['id'] ?? 0);
        if ($id <= 0) {
            return;
        }
        $national = null;
        if (array_key_exists('national_team', $team)) {
            $national = (int) (bool) $team['national_team'];
        }
        $st = $this->pdo->prepare(
            'INSERT INTO fd_sm_team (sm_id, name, short_code, country_id, national_team, image_path, raw_json)
             VALUES (:id, :name, :sc, :cid, :nat, :img, :raw)
             ON DUPLICATE KEY UPDATE
             name = VALUES(name), short_code = VALUES(short_code), country_id = VALUES(country_id),
             national_team = VALUES(national_team), image_path = VALUES(image_path), raw_json = VALUES(raw_json), updated_at = CURRENT_TIMESTAMP'
        );
        $st->execute([
            ':id' => $id,
            ':name' => isset($team['name']) ? (string) $team['name'] : null,
            ':sc' => isset($team['short_code']) ? (string) $team['short_code'] : null,
            ':cid' => isset($team['country_id']) ? (int) $team['country_id'] : null,
            ':nat' => $national,
            ':img' => isset($team['image_path']) ? (string) $team['image_path'] : null,
            ':raw' => json_encode($team, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * @param array<string,mixed> $venue
     */
    public function upsertVenue(array $venue): void
    {
        $id = (int) ($venue['id'] ?? 0);
        if ($id <= 0) {
            return;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO fd_sm_venue (sm_id, name, city_name, address, capacity, surface, raw_json)
             VALUES (:id, :name, :city, :addr, :cap, :surf, :raw)
             ON DUPLICATE KEY UPDATE
             name = VALUES(name), city_name = VALUES(city_name), address = VALUES(address),
             capacity = VALUES(capacity), surface = VALUES(surface), raw_json = VALUES(raw_json), updated_at = CURRENT_TIMESTAMP'
        );
        $st->execute([
            ':id' => $id,
            ':name' => isset($venue['name']) ? (string) $venue['name'] : null,
            ':city' => isset($venue['city_name']) ? (string) $venue['city_name'] : null,
            ':addr' => isset($venue['address']) ? (string) $venue['address'] : null,
            ':cap' => isset($venue['capacity']) ? (int) $venue['capacity'] : null,
            ':surf' => isset($venue['surface']) ? (string) $venue['surface'] : null,
            ':raw' => json_encode($venue, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * @param array<string,mixed> $ref
     */
    public function upsertReferee(array $ref): void
    {
        $id = (int) ($ref['id'] ?? 0);
        if ($id <= 0) {
            return;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO fd_sm_referee (sm_id, name, raw_json) VALUES (:id, :name, :raw)
             ON DUPLICATE KEY UPDATE name = VALUES(name), raw_json = VALUES(raw_json), updated_at = CURRENT_TIMESTAMP'
        );
        $st->execute([
            ':id' => $id,
            ':name' => isset($ref['name']) ? (string) $ref['name'] : (isset($ref['common_name']) ? (string) $ref['common_name'] : null),
            ':raw' => json_encode($ref, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getFixtureContext(int $fixtureSmId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT f.*, d.payload AS detail_payload
             FROM fd_sm_fixture f
             LEFT JOIN fd_sm_fixture_detail d ON d.fixture_sm_id = f.sm_id
             WHERE f.sm_id = :id LIMIT 1'
        );
        $st->execute([':id' => $fixtureSmId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $teams = [];
        foreach (['home_team_sm_id', 'away_team_sm_id'] as $col) {
            $tid = isset($row[$col]) ? (int) $row[$col] : 0;
            if ($tid > 0) {
                $t = $this->fetchTeam($tid);
                if ($t) {
                    $teams[$col] = $t;
                }
            }
        }

        $venue = null;
        if (!empty($row['venue_sm_id'])) {
            $venue = $this->fetchVenue((int) $row['venue_sm_id']);
        }

        $referee = null;
        if (!empty($row['referee_sm_id'])) {
            $referee = $this->fetchReferee((int) $row['referee_sm_id']);
        }

        $league = null;
        if (!empty($row['league_sm_id'])) {
            $league = $this->fetchLeague((int) $row['league_sm_id']);
        }

        $detail = null;
        if (!empty($row['detail_payload'])) {
            $decoded = json_decode((string) $row['detail_payload'], true);
            $detail = is_array($decoded) ? $decoded : null;
        }

        unset($row['detail_payload']);

        return [
            'fixture' => $row,
            'league' => $league,
            'teams' => $teams,
            'venue' => $venue,
            'referee' => $referee,
            'detail' => $detail,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchTeam(int $smId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM fd_sm_team WHERE sm_id = :id LIMIT 1');
        $st->execute([':id' => $smId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchVenue(int $smId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM fd_sm_venue WHERE sm_id = :id LIMIT 1');
        $st->execute([':id' => $smId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchReferee(int $smId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM fd_sm_referee WHERE sm_id = :id LIMIT 1');
        $st->execute([':id' => $smId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchLeague(int $smId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM fd_sm_league WHERE sm_id = :id LIMIT 1');
        $st->execute([':id' => $smId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /**
     * @param array<string,mixed> $participant
     */
    private function participantLocation(array $participant): ?string
    {
        if (isset($participant['meta']['location']) && is_string($participant['meta']['location'])) {
            return strtolower($participant['meta']['location']);
        }
        if (isset($participant['pivot']['location']) && is_string($participant['pivot']['location'])) {
            return strtolower($participant['pivot']['location']);
        }
        return null;
    }
}
