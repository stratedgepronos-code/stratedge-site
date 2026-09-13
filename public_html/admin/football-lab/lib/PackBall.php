<?php
declare(strict_types=1);
namespace StratEdgeLab;
require_once __DIR__ . '/DecisionEngine.php';
require_once __DIR__ . '/FootyStats.php';

/** The 46-column custom export supplied on 2026-09-09, paired with its column screenshot. */
final class PackBall
{
    public const PROFILE = 'packball-custom-gpt-46-v1';
    public const PROFILE_28 = 'packball-custom-gpt-28-20260914';

    public static function headers28(): array
    {
        return array_merge(array_slice(self::headers(), 0, 9), array_fill(0, 6, 'Odds'),
            array_fill(0, 3, 'Domicile | Extérieur'), array_fill(0, 5, 'Global'),
            array_fill(0, 3, 'Domicile | Extérieur'), ['Global', 'Global']);
    }

    public static function profile(array $headers): string
    {
        if ($headers === self::headers()) { return self::PROFILE; }
        if ($headers === self::headers28()) { return self::PROFILE_28; }
        throw new \InvalidArgumentException('Format PackBall non reconnu : conserver l’ordre des colonnes de ton export à 28 ou 46 colonnes.');
    }

    public static function headers(): array
    {
        return array_merge(
            ['Country', 'Short', 'League', 'Hour', 'Status', 'Home Team', 'Result Home', 'Result Visitor', 'Visitor Team', 'Result Home HT', 'Result Visitor HT'],
            array_fill(0, 8, 'Odds'),
            ['Domicile', 'Extérieur', 'Domicile', 'Extérieur', 'Domicile', 'Extérieur'],
            array_fill(0, 5, 'Global'),
            array_merge(...array_fill(0, 8, ['Domicile', 'Extérieur']))
        );
    }

    public static function mapping(string $profile = self::PROFILE): array
    {
        if ($profile === self::PROFILE_28) {
            return ['league' => '2:value', 'kickoff' => '3:value', 'status' => '4:value', 'home' => '5:value', 'away' => '8:value',
                'ft_over_15' => '9:value', 'ft_over_25' => '10:value', 'ft_over_35' => '11:value',
                'ft_under_15' => '12:value', 'ft_under_25' => '13:value', 'ft_under_35' => '14:value',
                'home_n' => '15:first', 'away_n' => '15:second', 'home_gf' => '16:first', 'away_gf' => '16:second',
                'home_ga' => '17:first', 'away_ga' => '17:second'];
        }
        // Zero-based indices. Keep repeated headers positional; do not deduplicate them.
        return [
            'league' => '2:value', 'kickoff' => '3:value', 'status' => '4:value',
            'home' => '5:value', 'away' => '8:value',
            'ft_over_25' => '11:value', 'ft_under_25' => '12:value',
            // In this layout UNDER 1.5 precedes OVER 1.5, unlike the other pairs.
            'ft_under_15' => '13:value', 'ft_over_15' => '14:value',
            'ft_over_35' => '15:value', 'ft_under_35' => '16:value',
            'ft_btts_yes' => '17:value', 'ft_btts_no' => '18:value',
            'home_n' => '19:value', 'away_n' => '20:value',
            'home_gf' => '21:value', 'away_gf' => '22:value',
            'home_ga' => '23:value', 'away_ga' => '24:value',
        ];
    }

    public static function prepare(string $csv, string $filename, ?\DateTimeImmutable $now = null, ?FootyStats $footy = null): array
    {
        $table = Engine::csv($csv, 'auto', true);
        $profile = self::profile($table['headers']);
        $table['name'] = substr(basename($filename), 0, 180);
        $table['sha256'] = hash('sha256', $csv);
        $table['profile'] = $profile;
        $mapping = self::mapping($profile);
        $options = [
            'date_format' => 'd-m-Y H:i', 'timezone' => 'Europe/Paris',
            'markets' => array_values(array_filter(array_keys($mapping), static function ($key) { return strpos($key, 'ft_') === 0; })),
            'require_odds' => true,
            'odds_source' => 'Export PackBall',
        ];
        $result = Engine::analyze(['stats' => $table], ['stats' => $mapping], $options, $now);
        foreach ($result['matches'] as &$match) { $match['country'] = trim((string)($table['rows'][$match['row'] - 1][0] ?? '')); }
        unset($match);
        if ($footy !== null) { $result = $footy->enrich($result); }
        $result = DecisionEngine::review($result, $table);
        foreach ($result['errors'] as &$error) {
            $row = $table['rows'][$error['row'] - 1] ?? null;
            if ($row) { $error['message'] = $row[5] . ' — ' . $row[8] . ' : ' . $error['message']; }
        }
        unset($error);
        return [
            'analysis' => $result, 'maps' => ['stats' => $mapping], 'tables' => ['stats' => $table],
            'options' => $options,
            'import' => ['profile' => $profile, 'filename' => $table['name'], 'rows' => count($table['rows']), 'timezone' => 'Europe/Paris'],
        ];
    }

    public static function replay(array $saved, FootyStats $footy): array
    {
        $table = $saved['tables']['stats'] ?? null;
        if (!is_array($table) || !is_array($table['headers'] ?? null) || !is_array($table['rows'] ?? null)) { throw new \InvalidArgumentException('CSV d’origine indisponible : importe de nouveau ton fichier.'); }
        self::profile($table['headers']);
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $table['headers'], ';', '"', '');
        foreach ($table['rows'] as $row) { fputcsv($stream, $row, ';', '"', ''); }
        rewind($stream); $csv = stream_get_contents($stream); fclose($stream);
        return self::prepare($csv, $saved['import']['filename'] ?? 'PackBall.csv', null, $footy);
    }
}
