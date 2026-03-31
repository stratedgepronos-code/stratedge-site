<?php
declare(strict_types=1);

/**
 * Client minimal SportMonks Football API v3.
 * @see https://docs.sportmonks.com/football/
 */
final class SportmonksClient
{
    private const BASE = 'https://api.sportmonks.com/v3/football';

    public function __construct(
        private readonly string $apiToken,
        private readonly int $timeoutSeconds = 45
    ) {
    }

    public static function fromEnv(): self
    {
        $token = getenv('SPORTMONKS_API_TOKEN') ?: '';
        if ($token === '' && defined('SPORTMONKS_API_TOKEN')) {
            $token = (string) SPORTMONKS_API_TOKEN;
        }
        if ($token === '') {
            throw new RuntimeException('SPORTMONKS_API_TOKEN manquant (local_config.php ou variable d’environnement).');
        }
        return new self($token);
    }

    /**
     * Fixtures entre deux dates (YYYY-MM-DD). Max 100 jours par appel.
     *
     * @return list<array<string,mixed>>
     */
    public function fixturesBetween(
        string $startDate,
        string $endDate,
        string $include = 'participants;scores;venue;state;league;season;referees',
        array $extraQuery = []
    ): array {
        $path = '/fixtures/between/' . $startDate . '/' . $endDate;
        $query = array_merge([
            'include' => $include,
            'per_page' => '50',
        ], $extraQuery);

        $out = [];
        $page = 1;
        do {
            $query['page'] = (string) $page;
            $body = $this->request($path, $query);
            $chunk = $body['data'] ?? [];
            if (!is_array($chunk)) {
                break;
            }
            foreach ($chunk as $row) {
                if (is_array($row)) {
                    $out[] = $row;
                }
            }
            $hasMore = (bool) ($body['pagination']['has_more'] ?? false);
            $page++;
            if ($page > 500) {
                break;
            }
        } while ($hasMore);

        return $out;
    }

    /**
     * Un fixture par ID (includes lourds possibles).
     *
     * @return array<string,mixed>|null
     */
    public function fixtureById(int $fixtureId, string $include): ?array
    {
        $path = '/fixtures/' . $fixtureId;
        $body = $this->request($path, [
            'include' => $include,
        ]);
        $data = $body['data'] ?? null;
        return is_array($data) ? $data : null;
    }

    /**
     * @param array<string,string|int|float|bool> $query
     * @return array<string,mixed>
     */
    public function request(string $path, array $query = [], int $attempt = 1): array
    {
        $query['api_token'] = $this->apiToken;
        $url = self::BASE . $path . '?' . http_build_query($query);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl_init a échoué');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('SportMonks HTTP erreur : ' . $err);
        }

        if ($code === 429 && $attempt < 6) {
            sleep(min(2 * $attempt, 30));
            return $this->request($path, $query, $attempt + 1);
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            throw new RuntimeException('Réponse JSON invalide (HTTP ' . $code . ')');
        }

        if ($code >= 400) {
            $msg = $json['message'] ?? $raw;
            throw new RuntimeException('SportMonks ' . $code . ' : ' . (is_string($msg) ? $msg : json_encode($msg)));
        }

        return $json;
    }
}
