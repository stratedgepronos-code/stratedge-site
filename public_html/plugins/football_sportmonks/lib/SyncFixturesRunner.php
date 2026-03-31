<?php
declare(strict_types=1);

/**
 * Logique de sync SportMonks (CLI + cron HTTP).
 */
final class SyncFixturesRunner
{
    private const INCLUDE_BASE = 'participants;scores;venue;state;league;season;referees';
    private const INCLUDE_ENRICH = 'participants;scores;venue;state;league;season;referees;lineups.details.type;events;statistics.type';

    public function __construct(
        private readonly PDO $pdo,
        private readonly SportmonksClient $client,
        private readonly FootballDataStore $store
    ) {
    }

    /**
     * @param callable|null $log function(string $line): void
     * @return array{total:int, errors:list<string>, message:string, log_id:int}
     */
    public function run(string $start, string $end, bool $enrich, ?callable $log = null): array
    {
        $tStart = strtotime($start . ' UTC');
        $tEnd = strtotime($end . ' UTC');
        if ($tStart === false || $tEnd === false || $tStart > $tEnd) {
            throw new InvalidArgumentException('Plage de dates incohérente.');
        }

        $stLog = $this->pdo->prepare(
            'INSERT INTO fd_sm_sync_log (action, status, message) VALUES (:a, :s, :m)'
        );
        $stLog->execute([
            ':a' => 'sync_fixtures ' . $start . '..' . $end . ($enrich ? ' +enrich' : ''),
            ':s' => 'running',
            ':m' => null,
        ]);
        $logId = (int) $this->pdo->lastInsertId();

        $total = 0;
        $errors = [];

        try {
            $cursor = $tStart;
            while ($cursor <= $tEnd) {
                $chunkEnd = min($cursor + 99 * 86400, $tEnd);
                $from = gmdate('Y-m-d', $cursor);
                $to = gmdate('Y-m-d', $chunkEnd);

                if ($log) {
                    $log("Fetch fixtures $from .. $to\n");
                }

                $fixtures = $this->client->fixturesBetween($from, $to, self::INCLUDE_BASE);
                $this->store->beginTransaction();
                try {
                    foreach ($fixtures as $item) {
                        $this->store->upsertFixtureTree($item, true);
                        $total++;
                    }
                    $this->store->commit();
                } catch (Throwable $e) {
                    $this->store->rollBack();
                    throw $e;
                }

                if ($enrich) {
                    foreach ($fixtures as $item) {
                        $fid = (int) ($item['id'] ?? 0);
                        if ($fid <= 0) {
                            continue;
                        }
                        try {
                            $full = $this->client->fixtureById($fid, self::INCLUDE_ENRICH);
                            if ($full !== null) {
                                $this->store->beginTransaction();
                                try {
                                    $this->store->upsertFixtureTree($full, true);
                                    $this->store->commit();
                                } catch (Throwable $e) {
                                    $this->store->rollBack();
                                    throw $e;
                                }
                            }
                        } catch (Throwable $e) {
                            $errors[] = $fid . ': ' . $e->getMessage();
                        }
                    }
                }

                $cursor = $chunkEnd + 86400;
            }

            $msg = $total . ' match(s) synchronisé(s).';
            if ($errors !== []) {
                $msg .= ' Avertissements enrich : ' . count($errors);
            }
            $this->pdo->prepare(
                'UPDATE fd_sm_sync_log SET finished_at = NOW(), status = :s, message = :m, meta = :meta WHERE id = :id'
            )->execute([
                ':s' => 'ok',
                ':m' => $msg,
                ':meta' => json_encode(['count' => $total, 'errors' => array_slice($errors, 0, 50)], JSON_UNESCAPED_UNICODE),
                ':id' => $logId,
            ]);

            return ['total' => $total, 'errors' => $errors, 'message' => $msg, 'log_id' => $logId];
        } catch (Throwable $e) {
            $this->pdo->prepare(
                'UPDATE fd_sm_sync_log SET finished_at = NOW(), status = :s, message = :m WHERE id = :id'
            )->execute([
                ':s' => 'error',
                ':m' => $e->getMessage(),
                ':id' => $logId,
            ]);
            throw $e;
        }
    }
}
