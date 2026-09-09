<?php
declare(strict_types=1);
namespace StratEdgeLab;

final class Metrics
{
    public static function summarize(array $entries): array
    {
        // First timestamped prediction per fixture: repeated imports are not additional bets.
        usort($entries, static function ($a, $b) { return strcmp($a['created_at'], $b['created_at']) ?: strcmp($a['run_id'], $b['run_id']); });
        $seen = []; $rows = []; $n = 0; $wins = 0; $priced = 0; $units = 0.0; $brier = 0.0; $logloss = 0.0; $bands = [];
        foreach ($entries as $entry) {
            $m = $entry['match'];
            if (!$m['pick'] || isset($seen[$m['key']])) { continue; }
            $seen[$m['key']] = true;
            $rows[] = $entry;
            $outcome = $entry['result']['outcome'] ?? '';
            if (!in_array($outcome, ['won', 'lost'], true)) { continue; }
            $y = $outcome === 'won' ? 1 : 0;
            $p = $m['pick']['probability'];
            $n++; $wins += $y; $brier += ($p - $y) ** 2;
            $clipped = max(1e-12, min(1 - 1e-12, $p));
            $logloss += -($y * log($clipped) + (1 - $y) * log(1 - $clipped));
            if ($m['pick']['odds'] !== null) { $priced++; $units += $y ? $m['pick']['odds'] - 1 : -1; }
            $band = min(9, (int)floor($p * 10));
            if (!isset($bands[$band])) { $bands[$band] = ['n' => 0, 'p' => 0, 'wins' => 0]; }
            $bands[$band]['n']++; $bands[$band]['p'] += $p; $bands[$band]['wins'] += $y;
        }
        ksort($bands);
        return ['rows' => $rows, 'n' => $n, 'wins' => $wins, 'priced' => $priced, 'units' => $units, 'roi' => $priced ? $units / $priced : null, 'brier' => $n ? $brier / $n : null, 'logloss' => $n ? $logloss / $n : null, 'bands' => $bands];
    }
}
