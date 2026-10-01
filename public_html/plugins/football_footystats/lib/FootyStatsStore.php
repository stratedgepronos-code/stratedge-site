<?php
declare(strict_types=1);

/**
 * Persistance des réponses FootyStats (fd_fy_*).
 */
final class FootyStatsStore
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string,mixed> $row Élément renvoyé par todays-matches (data[])
     */
    public function upsertMatchFromList(array $row): void
    {
        $id = isset($row['id']) ? (int) $row['id'] : 0;
        if ($id <= 0) {
            return;
        }

        $du = isset($row['date_unix']) ? (int) $row['date_unix'] : null;
        $dt = null;
        if ($du !== null && $du > 0) {
            $dt = gmdate('Y-m-d H:i:s', $du);
        }

        $listJson = json_encode($row, JSON_UNESCAPED_UNICODE);
        if ($listJson === false) {
            return;
        }

        $sql = 'INSERT INTO fd_fy_match (
            fy_match_id, date_unix, match_datetime, home_team_fy_id, away_team_fy_id,
            home_name, away_name, status, competition_id, league_name, country,
            home_goals, away_goals, list_json
        ) VALUES (
            :id, :du, :dt, :hid, :aid, :hn, :an, :st, :cid, :ln, :co, :hg, :ag, :lj
        ) ON DUPLICATE KEY UPDATE
            date_unix = VALUES(date_unix),
            match_datetime = VALUES(match_datetime),
            home_team_fy_id = VALUES(home_team_fy_id),
            away_team_fy_id = VALUES(away_team_fy_id),
            home_name = VALUES(home_name),
            away_name = VALUES(away_name),
            status = VALUES(status),
            competition_id = VALUES(competition_id),
            league_name = VALUES(league_name),
            country = VALUES(country),
            home_goals = VALUES(home_goals),
            away_goals = VALUES(away_goals),
            list_json = VALUES(list_json),
            updated_at = CURRENT_TIMESTAMP';

        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':id' => $id,
            ':du' => $du,
            ':dt' => $dt,
            ':hid' => isset($row['homeID']) ? (int) $row['homeID'] : null,
            ':aid' => isset($row['awayID']) ? (int) $row['awayID'] : null,
            ':hn' => isset($row['home_name']) ? (string) $row['home_name'] : null,
            ':an' => isset($row['away_name']) ? (string) $row['away_name'] : null,
            ':st' => isset($row['status']) ? (string) $row['status'] : null,
            ':cid' => isset($row['competition_id']) ? (int) $row['competition_id'] : null,
            ':ln' => isset($row['league_name']) ? (string) $row['league_name'] : (isset($row['competition']) ? (string) $row['competition'] : null),
            ':co' => isset($row['country']) ? (string) $row['country'] : null,
            ':hg' => isset($row['homeGoalCount']) ? (int) $row['homeGoalCount'] : null,
            ':ag' => isset($row['awayGoalCount']) ? (int) $row['awayGoalCount'] : null,
            ':lj' => $listJson,
        ]);
    }

    /**
     * Réponse brute de /matches?matchId= — stocke dans detail_json et met à jour les champs si présents.
     *
     * @param array<string,mixed> $apiResponse
     */
    public function mergeMatchDetail(int $fyMatchId, array $apiResponse): void
    {
        $detailJson = json_encode($apiResponse, JSON_UNESCAPED_UNICODE);
        if ($detailJson === false) {
            return;
        }

        $row = null;
        if (isset($apiResponse['data']) && is_array($apiResponse['data'])) {
            $row = $apiResponse['data'];
        } elseif (isset($apiResponse[0]) && is_array($apiResponse[0])) {
            $row = $apiResponse[0];
        } elseif (isset($apiResponse['id'])) {
            $row = $apiResponse;
        }

        if (is_array($row)) {
            $this->upsertMatchFromList($row);
        }

        $st = $this->pdo->prepare(
            'UPDATE fd_fy_match SET detail_json = :d, updated_at = CURRENT_TIMESTAMP WHERE fy_match_id = :id'
        );
        $st->execute([':d' => $detailJson, ':id' => $fyMatchId]);

        if ($st->rowCount() === 0) {
            $ins = $this->pdo->prepare(
                'INSERT INTO fd_fy_match (fy_match_id, detail_json) VALUES (:id, :d)
                 ON DUPLICATE KEY UPDATE detail_json = VALUES(detail_json), updated_at = CURRENT_TIMESTAMP'
            );
            $ins->execute([':id' => $fyMatchId, ':d' => $detailJson]);
        }
    }

    /**
     * @param array<string,mixed> $league
     */
    public function upsertLeague(array $league): void
    {
        $id = isset($league['id']) ? (int) $league['id'] : 0;
        if ($id <= 0) {
            return;
        }
        $raw = json_encode($league, JSON_UNESCAPED_UNICODE);
        if ($raw === false) {
            return;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO fd_fy_league (fy_league_id, name, country, season, raw_json) VALUES (:id, :n, :c, :s, :j)
             ON DUPLICATE KEY UPDATE name = VALUES(name), country = VALUES(country), season = VALUES(season), raw_json = VALUES(raw_json), updated_at = CURRENT_TIMESTAMP'
        );
        $st->execute([
            ':id' => $id,
            ':n' => isset($league['name']) ? (string) $league['name'] : null,
            ':c' => isset($league['country']) ? (string) $league['country'] : null,
            ':s' => isset($league['season']) ? (string) $league['season'] : null,
            ':j' => $raw,
        ]);
    }

    /**
     * @param array<string,mixed> $teamPayload Réponse API team (souvent { data: {...} })
     */
    public function upsertTeamPayload(array $teamPayload): void
    {
        $t = $teamPayload['data'] ?? $teamPayload;
        if (!is_array($t) || !isset($t['id'])) {
            return;
        }
        $id = (int) $t['id'];
        if ($id <= 0) {
            return;
        }
        $raw = json_encode($teamPayload, JSON_UNESCAPED_UNICODE);
        if ($raw === false) {
            return;
        }
        $name = isset($t['name']) ? (string) $t['name'] : null;
        $st = $this->pdo->prepare(
            'INSERT INTO fd_fy_team (fy_team_id, name, raw_json) VALUES (:id, :n, :j)
             ON DUPLICATE KEY UPDATE name = VALUES(name), raw_json = VALUES(raw_json), updated_at = CURRENT_TIMESTAMP'
        );
        $st->execute([':id' => $id, ':n' => $name, ':j' => $raw]);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getMatchContext(int $fyMatchId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM fd_fy_match WHERE fy_match_id = :id LIMIT 1');
        $st->execute([':id' => $fyMatchId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $list = null;
        if (!empty($row['list_json'])) {
            $list = json_decode((string) $row['list_json'], true);
        }
        $detail = null;
        if (!empty($row['detail_json'])) {
            $detail = json_decode((string) $row['detail_json'], true);
        }

        $homeTeam = null;
        $awayTeam = null;
        $hid = isset($row['home_team_fy_id']) ? (int) $row['home_team_fy_id'] : 0;
        $aid = isset($row['away_team_fy_id']) ? (int) $row['away_team_fy_id'] : 0;
        if ($hid > 0) {
            $homeTeam = $this->fetchTeam($hid);
        }
        if ($aid > 0) {
            $awayTeam = $this->fetchTeam($aid);
        }

        unset($row['list_json'], $row['detail_json']);

        return [
            'match' => $row,
            'list' => is_array($list) ? $list : null,
            'detail' => is_array($detail) ? $detail : null,
            'teams' => [
                'home' => $homeTeam,
                'away' => $awayTeam,
            ],
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchTeam(int $fyTeamId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM fd_fy_team WHERE fy_team_id = :id LIMIT 1');
        $st->execute([':id' => $fyTeamId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }
}
