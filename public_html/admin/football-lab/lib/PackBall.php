<?php
declare(strict_types=1);
namespace StratEdgeLab;
require_once __DIR__ . '/DecisionEngine.php';
require_once __DIR__ . '/FootyStats.php';

/** The 46-column custom export supplied on 2026-09-09, paired with its column screenshot. */
final class PackBall
{
    public const PROFILE = 'packball-custom-gpt-46-v1';
    public const PROFILE_33 = 'packball-custom-gpt-33-20260915';
    public const PROFILE_28 = 'packball-custom-gpt-28-20260914';

    public static function headers28(): array
    {
        return array_merge(array_slice(self::headers(), 0, 9), array_fill(0, 6, 'Odds'),
            array_fill(0, 3, 'Domicile | Extérieur'), array_fill(0, 5, 'Global'),
            array_fill(0, 3, 'Domicile | Extérieur'), ['Global', 'Global']);
    }

    public static function headers33(): array
    {
        return array_merge(array_slice(self::headers(), 0, 9), array_fill(0, 8, 'Odds'),
            array_fill(0, 3, 'Domicile | Extérieur'), array_fill(0, 5, 'Global'), array_fill(0, 8, 'Domicile | Extérieur'));
    }

    public static function csvText(array $table): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $table['headers'], ';', '"', '');
        foreach ($table['rows'] as $row) { fputcsv($stream, $row, ';', '"', ''); }
        rewind($stream); $csv = stream_get_contents($stream); fclose($stream);
        return $csv;
    }

    public static function expand33(array $row): array
    {
        $out = array_merge(array_slice($row, 0, 9), ['', ''], array_slice($row, 9, 8));
        foreach ([17,18,19] as $col) { $out = array_merge($out, array_map('trim', explode('|', $row[$col]))); }
        $out = array_merge($out, array_slice($row, 20, 5));
        foreach (range(25,32) as $col) { $out = array_merge($out, array_map('trim', explode('|', $row[$col]))); }
        if (count($out) !== 46) { throw new \InvalidArgumentException('GPT : une cellule Domicile | Extérieur doit contenir les deux valeurs séparées par |.'); }
        return $out;
    }

    public static function preparePair(array $files, ?\DateTimeImmutable $now = null, ?FootyStats $footy = null): array
    {
        if (count($files) !== 2) { throw new \InvalidArgumentException('Choisis les deux CSV : GPT et GPT-2 pour la même journée.'); }
        $sources = []; $indexed = [];
        foreach ($files as $file) {
            $table = Engine::csv($file['csv'], 'auto', true); $profile = self::profile($table['headers']);
            if (!in_array($profile, [self::PROFILE_33,self::PROFILE_28], true) || isset($sources[$profile])) {
                throw new \InvalidArgumentException('Il faut un export GPT à 33 colonnes et un GPT-2 à 28 colonnes.');
            }
            $table['name'] = substr(basename($file['name']),0,180); $table['sha256'] = hash('sha256',$file['csv']);
            $sources[$profile] = $table; $indexed[$profile] = [];
            foreach ($table['rows'] as $row) {
                $identity = Engine::identity($row,self::mapping($profile),['date_format'=>'d-m-Y H:i','timezone'=>'Europe/Paris']);
                $key = $identity['key'];
                if (isset($indexed[$profile][$key])) { throw new \InvalidArgumentException('Match en double dans ' . $table['name'] . ' : ' . $row[5] . '.'); }
                $indexed[$profile][$key] = $row;
            }
        }
        $primary = $indexed[self::PROFILE_33]; $secondary = $indexed[self::PROFILE_28];
        if (array_diff_key($primary,$secondary) || array_diff_key($secondary,$primary)) {
            throw new \InvalidArgumentException('Les deux exports ne contiennent pas les mêmes matchs. Réexporte GPT et GPT-2 pour la même journée et la même liste.');
        }
        foreach ($primary as $key=>$row) {
            $other = $secondary[$key];
            foreach ([0=>0,2=>2,4=>4,6=>6,7=>7,17=>15,18=>16,19=>17,9=>10,10=>13,11=>12,12=>9,13=>11,14=>14,22=>20] as $a=>$b) {
                if (preg_replace('/\s+/u','', $row[$a]) !== preg_replace('/\s+/u','', $other[$b])) {
                    throw new \InvalidArgumentException($row[5] . ' — ' . $row[8] . ' : données ou cotes différentes entre GPT et GPT-2. Réexporte les deux ensemble.');
                }
            }
        }
        $merged = ['headers'=>self::headers(), 'rows'=>array_map([self::class,'expand33'],array_values($primary))];
        $filename = $sources[self::PROFILE_33]['name'] . ' + ' . $sources[self::PROFILE_28]['name'];
        $prepared = self::prepare(self::csvText($merged),$filename,$now,$footy);
        $prepared['sources'] = $sources;
        $prepared['import']['profile'] = 'packball-gpt-pair-33-28';
        $prepared['import']['filename'] = $filename;
        foreach ($prepared['analysis']['matches'] as &$match) {
            $raw = $primary[$match['key']]; $extra = $secondary[$match['key']];
            $match['packball'] = ['scope'=>'Historiques globaux des deux équipes, pas des bilans par lieu',
                'gpt_features'=>DecisionEngine::features(self::expand33($raw))['values'],
                'gpt2'=>['over15_percent'=>$extra[18],'over05_percent'=>$extra[19],'over25_percent'=>$extra[20],
                    'over35_percent'=>$extra[21],'over45_percent'=>$extra[22], 'cv_home_away'=>$extra[23],
                    'shots_home_away'=>$extra[24], 'shots_on_target_against_home_away'=>$extra[25],
                    'second_half_over05_percent'=>$extra[26], 'second_half_over15_percent'=>$extra[27]]];
        }
        unset($match);
        return $prepared;
    }

    public static function profile(array $headers): string
    {
        if ($headers === self::headers()) { return self::PROFILE; }
        if ($headers === self::headers33()) { return self::PROFILE_33; }
        if ($headers === self::headers28()) { return self::PROFILE_28; }
        throw new \InvalidArgumentException('Format PackBall non reconnu : conserver l’ordre des colonnes de ton export à 28, 33 ou 46 colonnes.');
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
        if ($profile === self::PROFILE_33) {
            $map = self::mapping();
            foreach ($map as $key=>$value) {
                if (strpos($key,'ft_')===0) { $map[$key] = ((int)$value - 2) . ':value'; }
            }
            foreach (['n'=>17,'gf'=>18,'ga'=>19] as $field=>$col) { $map['home_'.$field]=$col.':first'; $map['away_'.$field]=$col.':second'; }
            return $map;
        }
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
        if ($profile === self::PROFILE_33) {
            return self::prepare(self::csvText(['headers'=>self::headers(),'rows'=>array_map([self::class,'expand33'],$table['rows'])]),$filename,$now,$footy);
        }
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
        if (isset($saved['sources'][self::PROFILE_33],$saved['sources'][self::PROFILE_28])) {
            $files = [];
            foreach ($saved['sources'] as $source) { $files[]=['csv'=>self::csvText($source),'name'=>$source['name']]; }
            return self::preparePair($files,null,$footy);
        }
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
