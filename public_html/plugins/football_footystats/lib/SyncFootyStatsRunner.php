<?php
declare(strict_types=1);

final class SyncFootyStatsRunner
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly FootyStatsClient $client,
        private readonly FootyStatsStore $store
    ) {
    }

    /**
     * @param callable|null $log
     * @return array{matches_upserted:int, dates:int, message:string, log_id:int, enrich_errors:int}
     */
    public function syncDateRange(string $startYmd, string $endYmd, bool $enrichMatches, bool $syncLeagues, ?callable $log = null): array
    {
        $t0 = strtotime($startYmd . ' UTC');
        $t1 = strtotime($endYmd . ' UTC');
        if ($t0 === false || $t1 === false || $t0 > $t1) {
            throw new InvalidArgumentException('Plage de dates invalide.');
        }

        $stLog = $this->pdo->prepare(
            'INSERT INTO fd_fy_sync_log (action, status, message) VALUES (:a, :s, :m)'
        );
        $stLog->execute([
            ':a' => 'sync ' . $startYmd . '..' . $endYmd . ($enrichMatches ? ' +enrich' : '') . ($syncLeagues ? ' +leagues' : ''),
            ':s' => 'running',
            ':m' => null,
        ]);
        $logId = (int) $this->pdo->lastInsertId();

        $totalMatches = 0;
        $enrichErr = 0;
        $datesCount = 0;

        try {
            if ($syncLeagues) {
                if ($log) {
                    $log("league-list\n");
                }
                $list = $this->client->leagueList(true);
                $data = $list['data'] ?? [];
                if (is_array($data)) {
                    foreach ($data as $L) {
                        if (is_array($L)) {
                            $this->store->upsertLeague($L);
                        }
                    }
                }
            }

            for ($t = $t0; $t <= $t1; $t += 86400) {
                $date = gmdate('Y-m-d', $t);
                $datesCount++;
                if ($log) {
                    $log("todays-matches $date\n");
                }

                $resp = $this->client->todaysMatches($date);
                $matches = $resp['data'] ?? [];
                if (!is_array($matches)) {
                    continue;
                }

                $this->pdo->beginTransaction();
                try {
                    foreach ($matches as $m) {
                        if (!is_array($m)) {
                            continue;
                        }
                        $this->store->upsertMatchFromList($m);
                        $totalMatches++;
                    }
                    $this->pdo->commit();
                } catch (Throwable $e) {
                    $this->pdo->rollBack();
                    throw $e;
                }

                if ($enrichMatches) {
                    foreach ($matches as $m) {
                        if (!is_array($m) || empty($m['id'])) {
                            continue;
                        }
                        $mid = (int) $m['id'];
                        try {
                            $detail = $this->client->matchById($mid);
                            $this->store->mergeMatchDetail($mid, $detail);
                        } catch (Throwable $e) {
                            $enrichErr++;
                        }
                    }
                }
            }

            $msg = $datesCount . ' jour(s), ' . $totalMatches . ' ligne(s) match traitée(s).';
            if ($enrichErr > 0) {
                $msg .= ' Enrichissements en échec : ' . $enrichErr;
            }

            $this->pdo->prepare(
                'UPDATE fd_fy_sync_log SET finished_at = NOW(), status = :s, message = :m, meta = :meta WHERE id = :id'
            )->execute([
                ':s' => 'ok',
                ':m' => $msg,
                ':meta' => json_encode([
                    'matches_upserted' => $totalMatches,
                    'dates' => $datesCount,
                    'enrich_errors' => $enrichErr,
                ], JSON_UNESCAPED_UNICODE),
                ':id' => $logId,
            ]);

            return [
                'matches_upserted' => $totalMatches,
                'dates' => $datesCount,
                'message' => $msg,
                'log_id' => $logId,
                'enrich_errors' => $enrichErr,
            ];
        } catch (Throwable $e) {
            $this->pdo->prepare(
                'UPDATE fd_fy_sync_log SET finished_at = NOW(), status = :s, message = :m WHERE id = :id'
            )->execute([
                ':s' => 'error',
                ':m' => $e->getMessage(),
                ':id' => $logId,
            ]);
            throw $e;
        }
    }
}
