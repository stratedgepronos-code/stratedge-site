<?php
declare(strict_types=1);
namespace StratEdgeLab;

/** Experimental decision policy. No claim of fitted/calibrated football probabilities. */
final class DecisionEngine
{
    public const VERSION = '2.0.0-gamma-poisson-experimental';
    public const FOOTY_VERSION = '2.2.0-footystats-diagnostics-experimental';
    public const MIN_ODDS = 1.60;
    public const MAX_ODDS = 3.50;
    public const PRIOR_MATCHES = 4.0;

    /** Read saved decisions only: viewing an archive never reruns a model or API. */
    public static function diagnostic(array $match): array
    {
        $fs = $match['footystats'] ?? null;
        $issues = array_values(array_unique($match['assessment']['issues'] ?? []));
        $state = 'no_bet'; $category = 'criteria_not_met';
        $title = 'Pari analysé, critères non atteints';
        $message = '';
        if ($match['pick'] ?? null) {
            $state = 'candidate'; $category = 'selected'; $title = $match['pick']['label'];
            $message = 'Prix et statistiques passent les contrôles. Le contexte reste à vérifier.';
        } elseif ($fs !== null && ($fs['status'] ?? '') !== 'enriched') {
            $state = 'data_missing'; $category = $fs['failure_code'] ?? self::failureCategory($fs['message'] ?? '');
            $title = 'Analyse impossible faute de données';
            $message = $fs['message'] ?? 'Enrichissement FootyStats à compléter.';
        } elseif ($issues) {
            $state = 'data_missing'; $category = 'data_incomplete';
            if (min($fs['home']['n'] ?? $match['stats']['home_n'] ?? 0, $fs['away']['n'] ?? $match['stats']['away_n'] ?? 0) < 8) { $category = 'sample_insufficient'; }
            $title = 'Analyse impossible faute de données'; $message = implode(' ', $issues);
        } else {
            $priced = array_values(array_filter($match['candidates'] ?? [], static function ($c) { return isset($c['odds']) && $c['odds'] > 1; }));
            if (!$priced) {
                $state = 'price_missing'; $category = 'price_missing'; $title = 'Analyse impossible : cotes manquantes';
                $message = 'Aucun marché ne possède une cote exploitable dans cet export.';
            } else {
                usort($priced, static function ($a, $b) { return (count($a['reasons'] ?? []) <=> count($b['reasons'] ?? [])) ?: (($b['stress_ev'] ?? -INF) <=> ($a['stress_ev'] ?? -INF)); });
                $closest = $priced[0];
                $message = count($priced) . ' marchés cotés examinés. ' . ($closest['label'] ?? '') . ' : ' . implode(' ', $closest['reasons'] ?? []);
            }
        }
        return ['state' => $state, 'category' => $category, 'title' => $title, 'message' => $message,
            'issues' => $issues, 'probability_scope' => $state === 'data_missing' ? 'descriptive_only' : 'experimental_model'];
    }

    /** Compatibility classification for archives predating structured failure codes. */
    public static function failureCategory(string $message): string
    {
        if (strpos($message, 'correspondance') !== false || strpos($message, 'Correspondance') !== false) { return 'api_match_missing'; }
        // Older message conflated zero samples with missing goal totals: do not invent which occurred.
        if (strpos($message, 'Échantillon FootyStats vide ou') !== false) { return 'sample_or_data_incomplete'; }
        if (strpos($message, 'échantillon') !== false || strpos($message, 'Échantillon') !== false) { return 'sample_insufficient'; }
        if (strpos($message, 'incomplet') !== false || strpos($message, 'incohérent') !== false || strpos($message, 'inexploitable') !== false) { return 'data_incomplete'; }
        return 'api_unavailable';
    }

    public static function diagnosticExport(array $analysis): array
    {
        $out = ['schema_version' => '1.0', 'method_version' => $analysis['version'] ?? null,
            'generated_at' => $analysis['generated_at'] ?? null, 'exported_at' => gmdate('c'),
            'selection_policy' => $analysis['selection_policy'] ?? null, 'import_errors' => $analysis['errors'] ?? [], 'matches' => []];
        foreach ($analysis['matches'] ?? [] as $match) {
            $fs = $match['footystats'] ?? []; $diagnostic = self::diagnostic($match);
            $item = array_intersect_key($match, array_flip(['key','home','away','league','kickoff','pick','packball','packball_stats','historical_context']));
            $item['diagnostic'] = $diagnostic;
            $item['sample'] = ['scope' => 'home_for_host_away_for_visitor',
                'home_n' => $fs['home']['n'] ?? $fs['sample']['home_n'] ?? null,
                'away_n' => $fs['away']['n'] ?? $fs['sample']['away_n'] ?? null,
                'minimum_each' => 8];
            $item['source'] = $fs['source'] ?? ['provider' => 'FootyStats', 'competition' => isset($fs['season_id']) ? ($match['league'] ?? null) : null,
                'season_id' => $fs['season_id'] ?? null, 'as_of' => $fs['as_of'] ?? null,
                'period' => 'Saison de la compétition ; dates détaillées non archivées'];
            $item['footystats'] = $fs;
            $item['markets'] = [];
            foreach ($match['candidates'] ?? [] as $candidate) {
                $c = array_intersect_key($candidate, array_flip(['id','label','odds','probability','stress_probability','fair_odds','minimum_price','ev','stress_ev','market_probability','overround','edge','eligible','reasons']));
                $c['probability_scope'] = $diagnostic['probability_scope'];
                // Retain older computations, but explicitly label them unusable for betting if data failed.
                $c['usable_for_selection'] = $diagnostic['state'] !== 'data_missing';
                $item['markets'][] = $c;
            }
            $out['matches'][] = $item;
        }
        return $out;
    }

    public static function features(array $row): array
    {
        if (count($row) === 28) {
            $values = self::features(array_fill(0, 46, ''))['values']; $issues = [];
            // Global is an aggregate of both teams' histories, never a venue split.
            try {
                $values['over25'] = Engine::number($row[20], 'fréquence +2,5', 0, 100);
                $values['under25'] = $values['over25'] === null ? null : 100 - $values['over25'];
                $previous = 100;
                foreach ([19, 18, 20, 21, 22] as $column) {
                    $v = Engine::number($row[$column], 'fréquence de buts', 0, 100);
                    if ($v !== null && $v > $previous) { $issues[] = 'Fréquences de buts non décroissantes : vérifier les colonnes.'; }
                    if ($v !== null) { $previous = $v; }
                }
            } catch (\InvalidArgumentException $e) { $issues[] = 'Fréquences PackBall invalides.'; }
            // The two shots icons are not sufficiently identified; keep their raw cells only.
            return ['values' => $values, 'issues' => $issues];
        }
        $columns = ['match_avg' => [25,20], 'league_avg' => [26,20], 'over25' => [27,100], 'under25' => [28,100], 'btts' => [29,100],
            'home_cs' => [30,100], 'away_cs' => [31,100], 'home_fts' => [32,100], 'away_fts' => [33,100],
            'home_shots_against' => [34,100], 'away_shots_against' => [35,100], 'home_shots' => [36,100], 'away_shots' => [37,100],
            'home_sot' => [38,100], 'away_sot' => [39,100], 'home_sot_against' => [40,100], 'away_sot_against' => [41,100],
            'home_possession' => [42,100], 'away_possession' => [43,100], 'home_ppg' => [44,3], 'away_ppg' => [45,3]];
        $values = []; $issues = [];
        foreach ($columns as $key => [$index, $max]) {
            try { $values[$key] = Engine::number($row[$index] ?? null, $key, 0, $max); }
            catch (\InvalidArgumentException $e) { $values[$key] = null; $issues[] = 'Statistique invalide : ' . $key; }
        }
        if ($values['over25'] !== null && $values['under25'] !== null && abs($values['over25'] + $values['under25'] - 100) > 1) { $issues[] = 'Les fréquences +2,5 / −2,5 ne totalisent pas 100 %.'; }
        foreach (['home', 'away'] as $side) {
            foreach (['' => '', '_against' => '_against'] as $suffix) {
                if ($values[$side . '_sot' . $suffix] !== null && $values[$side . '_shots' . $suffix] !== null && $values[$side . '_sot' . $suffix] > $values[$side . '_shots' . $suffix] + 0.15) { $issues[] = 'Tirs cadrés supérieurs aux tirs : vérifier les colonnes.'; }
            }
        }
        return ['values' => $values, 'issues' => array_values(array_unique($issues))];
    }

    public static function posterior(array $stats, float $leagueAverage, float $prior = self::PRIOR_MATCHES): array
    {
        if ($leagueAverage <= 0 || $prior <= 0) { throw new \InvalidArgumentException('Référence de buts invalide.'); }
        // Half weight for each aggregate: overlapping team samples are not 20 independent fixtures.
        $rate = $prior + 0.5 * ($stats['home_n'] + $stats['away_n']);
        $home = $prior * $leagueAverage / 2 + 0.5 * ($stats['home_n'] * $stats['home_gf'] + $stats['away_n'] * $stats['away_ga']);
        $away = $prior * $leagueAverage / 2 + 0.5 * ($stats['away_n'] * $stats['away_gf'] + $stats['home_n'] * $stats['home_ga']);
        return ['home' => ['shape' => $home, 'rate' => $rate], 'away' => ['shape' => $away, 'rate' => $rate]];
    }

    public static function pmf(float $shape, float $rate, int $max = 3): array
    {
        if ($shape <= 0 || $rate <= 0 || $max < 0) { throw new \InvalidArgumentException('Paramètres prédictifs invalides.'); }
        $out = [exp($shape * log($rate / ($rate + 1)))];
        for ($k = 1; $k <= $max; $k++) { $out[$k] = $out[$k - 1] * ($shape + $k - 1) / ($k * ($rate + 1)); }
        return $out;
    }

    public static function probabilities(array $posterior, float $factor = 1.0): array
    {
        $home = self::pmf($posterior['home']['shape'] * $factor, $posterior['home']['rate']);
        $away = self::pmf($posterior['away']['shape'] * $factor, $posterior['away']['rate']);
        $out = [];
        foreach ([1,2,3] as $limit) {
            $under = 0.0;
            for ($h = 0; $h <= $limit; $h++) { for ($a = 0; $a <= $limit - $h; $a++) { $under += $home[$h] * $away[$a]; } }
            $under = max(0.0, min(1.0, $under));
            $out['ft_under_' . $limit . '5'] = $under;
            $out['ft_over_' . $limit . '5'] = 1 - $under;
        }
        $out['ft_btts_yes'] = (1 - $home[0]) * (1 - $away[0]);
        $out['ft_btts_no'] = 1 - $out['ft_btts_yes'];
        return $out;
    }

    public static function review(array $analysis, array $table): array
    {
        foreach ($analysis['matches'] as &$match) {
            $data = self::features($table['rows'][$match['row'] - 1]); $f = $data['values']; $s = $match['stats'];
            $issues = $data['issues'];
            if (isset($match['footystats'])) {
                $fs = $match['footystats'];
                if ($fs['status'] !== 'enriched') { $issues[] = 'FootyStats : ' . $fs['message']; }
                else {
                    // Keep the original PackBall features separately. Do not mix sample periods.
                    $match['packball_features'] = $f;
                    $f = self::features(array_fill(0, 46, ''))['values'];
                    $f['league_avg'] = $fs['league_average'];
                    foreach (['home', 'away'] as $side) {
                        foreach (['cs', 'fts', 'shots', 'sot', 'possession', 'ppg'] as $key) { $f[$side . '_' . $key] = $fs[$side][$key]; }
                    }
                    foreach (['over25', 'btts'] as $key) {
                        $h = $fs['home'][$key]; $a = $fs['away'][$key];
                        $f[$key] = $h !== null && $a !== null ? ($h * $s['home_n'] + $a * $s['away_n']) / ($s['home_n'] + $s['away_n']) : null;
                    }
                    $f['under25'] = $f['over25'] === null ? null : 100 - $f['over25'];
                }
            }
            if ($f['league_avg'] === null || $f['league_avg'] <= 0) { $issues[] = 'Moyenne de buts de la ligue manquante.'; }
            if (min($s['home_n'], $s['away_n']) < 8) { $issues[] = 'Moins de huit matchs dans un des échantillons.'; }
            // Fallback allows a descriptive report, but a missing league reference blocks selections.
            $league = $f['league_avg'] !== null && $f['league_avg'] > 0 ? $f['league_avg'] : max(0.1, array_sum($match['lambdas']['ft']));
            $posterior = self::posterior($s, $league);
            $probabilities = self::probabilities($posterior);
            $scenarios = [self::probabilities($posterior, 0.85), self::probabilities($posterior, 1.15), self::probabilities(self::posterior($s, $league, 2)), self::probabilities(self::posterior($s, $league, 8))];
            $old = array_column($match['candidates'], null, 'id'); $candidates = []; $selected = [];
            foreach ($probabilities as $id => $probability) {
                $c = $old[$id]; $c['poisson_probability'] = $c['probability']; $c['probability'] = $probability;
                $c['stress_probability'] = min(array_merge([$probability], array_column($scenarios, $id)));
                $c['fair_odds'] = $probability > 0 ? 1 / $probability : null;
                $c['minimum_price'] = $c['stress_probability'] > 0 ? ceil(max(1.04 / $probability, 1 / $c['stress_probability']) * 100) / 100 : null;
                $c['ev'] = $c['odds'] === null ? null : $probability * $c['odds'] - 1;
                $c['stress_ev'] = $c['odds'] === null ? null : $c['stress_probability'] * $c['odds'] - 1;
                $opposite = str_replace(['_over_', '_under_'], ['_UNDER_', '_OVER_'], $id);
                $opposite = str_replace(['_UNDER_', '_OVER_'], ['_under_', '_over_'], $opposite);
                if ($id === 'ft_btts_yes') { $opposite = 'ft_btts_no'; } elseif ($id === 'ft_btts_no') { $opposite = 'ft_btts_yes'; }
                $otherOdds = $old[$opposite]['odds'] ?? null;
                $c['market_probability'] = null; $c['overround'] = null; $c['edge'] = null;
                if ($c['odds'] !== null && $otherOdds !== null) {
                    $sum = 1 / $c['odds'] + 1 / $otherOdds;
                    $c['overround'] = $sum - 1; $c['market_probability'] = (1 / $c['odds']) / $sum;
                    $c['edge'] = $probability - $c['market_probability'];
                }
                $c['observed_probability'] = null;
                if (in_array($id, ['ft_over_25', 'ft_under_25'], true) && $f['over25'] !== null) { $c['observed_probability'] = $id === 'ft_over_25' ? $f['over25'] / 100 : 1 - $f['over25'] / 100; }
                if (strpos($id, 'ft_btts_') === 0 && $f['btts'] !== null) { $c['observed_probability'] = $id === 'ft_btts_yes' ? $f['btts'] / 100 : 1 - $f['btts'] / 100; }
                $c['clean_sheet_reference'] = null;
                if (strpos($id, 'ft_btts_') === 0 && $f['home_fts'] !== null && $f['away_fts'] !== null && $f['home_cs'] !== null && $f['away_cs'] !== null) {
                    $yesReference = (1 - ($f['home_fts'] + $f['away_cs']) / 200) * (1 - ($f['away_fts'] + $f['home_cs']) / 200);
                    $c['clean_sheet_reference'] = $id === 'ft_btts_yes' ? $yesReference : 1 - $yesReference;
                }
                $reasons = $issues;
                if ($c['odds'] === null) { $reasons[] = 'Cote absente ou invalide.'; }
                elseif ($c['odds'] < self::MIN_ODDS) { $reasons[] = 'Cote inférieure à 1,60.'; }
                elseif ($c['odds'] > self::MAX_ODDS) { $reasons[] = 'Cote supérieure à 3,50.'; }
                if ($probability < 0.5) { $reasons[] = 'Probabilité estimée inférieure à 50 %.'; }
                if ($c['ev'] !== null && $c['ev'] < 0.04) { $reasons[] = 'Prix insuffisant : espérance du modèle sous +4 %.'; }
                if ($c['stress_ev'] !== null && $c['stress_ev'] < 0) { $reasons[] = 'Avantage perdu dans un scénario défavorable.'; }
                if ($c['market_probability'] === null) { $reasons[] = 'Cote opposée manquante : marché non comparable.'; }
                elseif ($c['overround'] < -0.02 || $c['overround'] > 0.20) { $reasons[] = 'Paire de cotes incohérente ou trop chargée.'; }
                elseif ($c['edge'] < 0.025) { $reasons[] = 'Écart au marché inférieur à 2,5 points.'; }
                if ($c['observed_probability'] !== null && abs($probability - $c['observed_probability']) > 0.25) { $reasons[] = 'Contradiction importante avec la fréquence observée.'; }
                if ($c['clean_sheet_reference'] !== null && abs($probability - $c['clean_sheet_reference']) > 0.25) { $reasons[] = 'Désaccord avec les clean sheets et les matchs sans marquer.'; }
                $c['reasons'] = array_values(array_unique($reasons)); $c['eligible'] = !$c['reasons'];
                $candidates[] = $c; if ($c['eligible']) { $selected[] = $c; }
            }
            usort($selected, static function ($a, $b) { return ($b['stress_ev'] <=> $a['stress_ev']) ?: (($b['ev'] <=> $a['ev']) ?: strcmp($a['id'], $b['id'])); });
            $match['pick'] = $selected[0] ?? null; $match['candidates'] = $candidates;
            $match['baseline_lambdas'] = $match['lambdas'];
            $match['lambdas']['ft'] = ['home' => $posterior['home']['shape'] / $posterior['home']['rate'], 'away' => $posterior['away']['shape'] / $posterior['away']['rate']];
            $match['assessment'] = ['features' => $f, 'issues' => $issues, 'posterior' => $posterior,
                'status' => $match['pick'] ? 'context_pending' : 'no_bet', 'version' => isset($analysis['footystats']) ? self::FOOTY_VERSION : self::VERSION,
                'explanation' => ''];
            $match['assessment']['explanation'] = self::diagnostic($match)['message'];
            $match['warnings'] = array_values(array_unique(array_merge($match['warnings'], $issues)));
        }
        unset($match);
        usort($analysis['matches'], static function ($a, $b) { return (($b['pick']['stress_ev'] ?? -INF) <=> ($a['pick']['stress_ev'] ?? -INF)) ?: strcmp($a['kickoff'], $b['kickoff']); });
        $analysis['version'] = isset($analysis['footystats']) ? self::FOOTY_VERSION : self::VERSION;
        $analysis['selection_policy'] = ['min_odds' => self::MIN_ODDS, 'max_odds' => self::MAX_ODDS, 'min_ev' => 0.04, 'min_edge' => 0.025, 'stress_factor' => 0.15, 'prior_matches' => self::PRIOR_MATCHES];
        return $analysis;
    }
}
