<?php
declare(strict_types=1);
namespace StratEdgeLab;
require_once __DIR__ . '/DecisionEngine.php';

/** The 46-column custom export supplied on 2026-09-09, paired with its column screenshot. */
final class PackBall
{
    public const PROFILE = 'packball-custom-gpt-46-v1';

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

    public static function mapping(): array
    {
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

    public static function prepare(string $csv, string $filename, ?\DateTimeImmutable $now = null): array
    {
        $table = Engine::csv($csv, 'auto', true);
        if ($table['headers'] !== self::headers()) {
            throw new \InvalidArgumentException('Ce fichier ne correspond pas au format PackBall configuré (46 colonnes). Réexporte le même tableau avec les statistiques et les cotes.');
        }
        $table['name'] = substr(basename($filename), 0, 180);
        $table['sha256'] = hash('sha256', $csv);
        $table['profile'] = self::PROFILE;
        $mapping = self::mapping();
        $options = [
            'date_format' => 'd-m-Y H:i', 'timezone' => 'Europe/Paris',
            'markets' => array_values(array_filter(array_keys($mapping), static function ($key) { return strpos($key, 'ft_') === 0; })),
            'require_odds' => true,
            'odds_source' => 'Export PackBall',
        ];
        $result = Engine::analyze(['stats' => $table], ['stats' => $mapping], $options, $now);
        $result = DecisionEngine::review($result, $table);
        foreach ($result['errors'] as &$error) {
            $row = $table['rows'][$error['row'] - 1] ?? null;
            if ($row) { $error['message'] = $row[5] . ' — ' . $row[8] . ' : ' . $error['message']; }
        }
        unset($error);
        return [
            'analysis' => $result, 'maps' => ['stats' => $mapping], 'tables' => ['stats' => $table],
            'options' => $options,
            'import' => ['profile' => self::PROFILE, 'filename' => $table['name'], 'rows' => count($table['rows']), 'timezone' => 'Europe/Paris'],
        ];
    }
}
