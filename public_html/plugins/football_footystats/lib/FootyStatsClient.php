<?php
declare(strict_types=1);

/**
 * Client FootyStats API (api.footystats.org) — aligné sur stats-api.php.
 */
final class FootyStatsClient
{
    private const BASE = 'https://api.footystats.org';

    public function __construct(
        private readonly string $apiKey,
        private readonly int $timeoutSeconds = 25
    ) {
    }

    public static function fromEnv(): self
    {
        $k = getenv('FOOTYSTATS_API_KEY') ?: '';
        if ($k === '' && defined('FOOTYSTATS_API_KEY')) {
            $k = (string) FOOTYSTATS_API_KEY;
        }
        if ($k === '' || $k === 'REPLACE-ME') {
            throw new RuntimeException('FOOTYSTATS_API_KEY manquant (config-keys.php).');
        }
        return new self($k);
    }

    /**
     * @return array<string,mixed>
     */
    public function todaysMatches(string $dateYmd): array
    {
        $url = self::BASE . '/todays-matches?key=' . rawurlencode($this->apiKey) . '&date=' . rawurlencode($dateYmd);
        return $this->request($url);
    }

    /**
     * @return array<string,mixed>
     */
    public function matchById(string|int $matchId): array
    {
        $url = self::BASE . '/matches?key=' . rawurlencode($this->apiKey) . '&matchId=' . rawurlencode((string) $matchId);
        return $this->request($url);
    }

    /**
     * @return array<string,mixed>
     */
    public function leagueList(bool $chosenOnly = true): array
    {
        $url = self::BASE . '/league-list?key=' . rawurlencode($this->apiKey) . '&chosen_leagues_only=' . ($chosenOnly ? 'true' : 'false');
        return $this->request($url);
    }

    /**
     * @return array<string,mixed>
     */
    public function teamById(int $teamId): array
    {
        $url = self::BASE . '/team?key=' . rawurlencode($this->apiKey) . '&team_id=' . $teamId;
        return $this->request($url);
    }

    /**
     * @return array<string,mixed>
     */
    public function headToHead(int $team1Id, int $team2Id): array
    {
        $url = self::BASE . '/head-to-head?key=' . rawurlencode($this->apiKey)
            . '&team1_id=' . $team1Id . '&team2_id=' . $team2Id;
        return $this->request($url);
    }

    /**
     * @return array<string,mixed>
     */
    private function request(string $url, int $attempt = 1): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl_init a échoué');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('FootyStats HTTP : ' . $err);
        }

        if ($code === 429 && $attempt < 5) {
            sleep(min(3 * $attempt, 20));
            return $this->request($url, $attempt + 1);
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            throw new RuntimeException('JSON invalide (HTTP ' . $code . ')');
        }

        if ($code !== 200) {
            $msg = $json['message'] ?? $json['error'] ?? substr($raw, 0, 200);
            throw new RuntimeException('FootyStats ' . $code . ' : ' . (is_string($msg) ? $msg : json_encode($msg)));
        }

        return $json;
    }
}
