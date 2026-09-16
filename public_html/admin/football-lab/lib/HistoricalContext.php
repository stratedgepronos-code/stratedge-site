<?php
declare(strict_types=1);
namespace StratEdgeLab;

/** Independent, descriptive venue histories. Never pooled into the pricing model. */
final class HistoricalContext
{
    public const VERSION = '1.0-detailed-venue-descriptive';

    public static function summarize(array $fixtures, int $teamId, string $venue, int $seasonId, int $cutoff): array
    {
        if (!in_array($venue, ['home', 'away'], true)) { throw new \InvalidArgumentException('Lieu historique invalide.'); }
        $selected = []; $lower = $cutoff - 365 * 86400;
        foreach ($fixtures as $fixture) {
            if ((int)($fixture[$venue . 'ID'] ?? 0) !== $teamId) { continue; }
            if (isset($fixture['competition_id']) && (int)$fixture['competition_id'] !== $seasonId) { throw new \RuntimeException('Compétition historique contradictoire.'); }
            $date = $fixture['date_unix'] ?? null;
            if (!is_numeric($date)) { throw new \RuntimeException('Date historique manquante.'); }
            // A completed match must be at least four hours before the snapshot; exclude neutral venues.
            if ($date < $lower || $date + 14400 > $cutoff || ($fixture['status'] ?? '') !== 'complete' || !empty($fixture['no_home_away'])) { continue; }
            foreach (['id', 'homeGoalCount', 'awayGoalCount'] as $field) {
                if (filter_var($fixture[$field] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => $field === 'id' ? 1 : 0]]) === false) {
                    throw new \RuntimeException('Score ou identifiant historique incomplet.');
                }
            }
            $id = (int)$fixture['id'];
            $row = ['id' => $id, 'date' => gmdate('c', (int)$date), 'home_goals' => (int)$fixture['homeGoalCount'], 'away_goals' => (int)$fixture['awayGoalCount']];
            if (isset($selected[$id]) && $selected[$id] !== $row) { throw new \RuntimeException('Match historique contradictoire.'); }
            $selected[$id] = $row;
        }
        usort($selected, static function ($a, $b) { return strcmp($b['date'], $a['date']) ?: ($b['id'] <=> $a['id']); });
        $selected = array_slice($selected, 0, 20); $n = count($selected); $gf = 0; $ga = 0; $over = 0;
        foreach ($selected as $row) {
            $gf += $row[$venue . '_goals']; $ga += $row[($venue === 'home' ? 'away' : 'home') . '_goals'];
            if ($row['home_goals'] + $row['away_goals'] >= 3) { $over++; }
        }
        return ['method_version' => self::VERSION, 'scope' => 'descriptive_only', 'venue' => $venue,
            'season_id' => $seasonId, 'team_id' => $teamId, 'as_of' => gmdate('c', $cutoff),
            'window_start' => gmdate('c', $lower), 'period_start' => $n ? $selected[$n - 1]['date'] : null,
            'period_end' => $n ? $selected[0]['date'] : null, 'n' => $n,
            'gf' => $n ? $gf / $n : null, 'ga' => $n ? $ga / $n : null,
            'over25_percent' => $n ? 100 * $over / $n : null, 'matches' => $selected];
    }
}
