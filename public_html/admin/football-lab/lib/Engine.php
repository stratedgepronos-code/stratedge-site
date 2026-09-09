<?php
declare(strict_types=1);

namespace StratEdgeLab;

final class Engine
{
    public const VERSION = '1.0.0-experimental';

    public static function fields(): array
    {
        return [
            'home' => 'Équipe domicile', 'away' => 'Équipe extérieur', 'kickoff' => 'Date / heure du match',
            'league' => 'Compétition', 'status' => 'Statut du match',
            'home_n' => 'Nombre de matchs — domicile', 'away_n' => 'Nombre de matchs — extérieur',
            'home_gf' => 'Domicile : buts marqués / match', 'home_ga' => 'Domicile : buts encaissés / match',
            'away_gf' => 'Extérieur : buts marqués / match', 'away_ga' => 'Extérieur : buts encaissés / match',
            'h1_home_gf' => '1re MT : domicile marque', 'h1_home_ga' => '1re MT : domicile encaisse',
            'h1_away_gf' => '1re MT : extérieur marque', 'h1_away_ga' => '1re MT : extérieur encaisse',
            'h2_home_gf' => '2e MT : domicile marque', 'h2_home_ga' => '2e MT : domicile encaisse',
            'h2_away_gf' => '2e MT : extérieur marque', 'h2_away_ga' => '2e MT : extérieur encaisse',
        ];
    }

    public static function markets(): array
    {
        $out = [];
        foreach (['ft' => 'Match', 'h1' => '1re mi-temps', 'h2' => '2e mi-temps'] as $period => $label) {
            $lines = $period === 'ft' ? [1.5, 2.5, 3.5] : [0.5, 1.5];
            foreach ($lines as $line) {
                foreach (['over' => 'Plus de', 'under' => 'Moins de'] as $side => $word) {
                    $id = $period . '_' . $side . '_' . str_replace('.', '', (string)$line);
                    $out[$id] = ['label' => $label . ' · ' . $word . ' ' . str_replace('.', ',', (string)$line) . ' buts', 'period' => $period, 'type' => $side, 'line' => $line, 'scope' => 'total'];
                }
            }
        }
        foreach (['yes' => 'Oui', 'no' => 'Non'] as $side => $label) {
            $out['ft_btts_' . $side] = ['label' => 'Les deux équipes marquent · ' . $label, 'period' => 'ft', 'type' => $side, 'scope' => 'btts'];
        }
        foreach (['home' => 'Domicile', 'away' => 'Extérieur'] as $scope => $label) {
            foreach ([0.5, 1.5] as $line) {
                foreach (['over' => 'Plus de', 'under' => 'Moins de'] as $side => $word) {
                    $out['ft_' . $scope . '_' . $side . '_' . str_replace('.', '', (string)$line)] = ['label' => $label . ' · ' . $word . ' ' . str_replace('.', ',', (string)$line) . ' buts', 'period' => 'ft', 'type' => $side, 'line' => $line, 'scope' => $scope];
                }
            }
        }
        return $out;
    }

    public static function csv(string $text, string $separator, bool $header): array
    {
        if ($text === '' || strlen($text) > 2097152) {
            throw new \InvalidArgumentException('Chaque CSV doit contenir entre 1 octet et 2 Mo.');
        }
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        if (strpos($text, "\0") !== false || preg_match('//u', $text) !== 1) {
            throw new \InvalidArgumentException('Exportez le fichier en CSV UTF-8.');
        }
        if ($separator === 'auto') {
            $first = strtok($text, "\r\n");
            $best = 0;
            foreach ([';', ',', "\t"] as $candidate) {
                $count = count(str_getcsv($first, $candidate, '"', ''));
                if ($count > $best) { $separator = $candidate; $best = $count; }
            }
        }
        if (!in_array($separator, [';', ',', "\t"], true)) {
            throw new \InvalidArgumentException('Séparateur non reconnu.');
        }
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $text);
        rewind($stream);
        $rows = [];
        $width = null;
        try {
            while (($row = fgetcsv($stream, 0, $separator, '"', '')) !== false) {
                if ($row === [null] || (count($row) === 1 && trim((string)$row[0]) === '')) { continue; }
                $row = array_map(static function ($v) { return trim((string)$v); }, $row);
                if ($width === null) { $width = count($row); }
                if (count($row) !== $width) {
                    throw new \InvalidArgumentException('Nombre de colonnes variable à la ligne ' . (count($rows) + 1) . '. Vérifiez le séparateur.');
                }
                if ($width > 160 || count($rows) >= 1001) {
                    throw new \InvalidArgumentException('Limite : 1 000 matchs et 160 colonnes par import.');
                }
                foreach ($row as $cell) {
                    if (strlen($cell) > 2000) { throw new \InvalidArgumentException('Une cellule dépasse 2 000 caractères.'); }
                }
                $rows[] = $row;
            }
        } finally { fclose($stream); }
        if (!$rows) { throw new \InvalidArgumentException('Fichier vide.'); }
        $headers = $header ? array_shift($rows) : array_map(static function ($n) { return 'Colonne ' . $n; }, range(1, $width));
        if (!$rows || count($rows) > 1000) { throw new \InvalidArgumentException('Le fichier doit contenir de 1 à 1 000 lignes de données.'); }
        return ['headers' => $headers, 'rows' => $rows, 'separator' => $separator, 'has_header' => $header, 'fingerprint' => hash('sha256', json_encode([$width, $separator, $header, $header ? $headers : []]))];
    }

    public static function cell(array $row, array $mapping, string $key): ?string
    {
        $spec = $mapping[$key] ?? '';
        if ($spec === '') { return null; }
        if (!is_string($spec) || !preg_match('/^(\d{1,3}):(value|first|second)$/', $spec, $m) || !array_key_exists((int)$m[1], $row)) {
            throw new \InvalidArgumentException('Colonne invalide pour ' . $key . '.');
        }
        $value = $row[(int)$m[1]];
        if ($m[2] !== 'value') {
            $parts = preg_split('/\s*\|\s*/u', $value);
            if (count($parts) !== 2) { throw new \InvalidArgumentException('La cellule de ' . $key . ' ne contient pas une paire A | B.'); }
            $value = $parts[$m[2] === 'first' ? 0 : 1];
        }
        return trim($value);
    }

    public static function number(?string $value, string $label, float $min = 0, float $max = 20): ?float
    {
        if ($value === null || $value === '' || in_array($value, ['-', '—', 'N/A'], true)) { return null; }
        $value = str_replace(',', '.', $value);
        if (!preg_match('/^\d+(?:\.\d+)?$/D', $value)) { throw new \InvalidArgumentException('Valeur numérique invalide : ' . $label . '.'); }
        $number = (float)$value;
        if (!is_finite($number) || $number < $min || $number > $max) { throw new \InvalidArgumentException('Valeur hors limites : ' . $label . '.'); }
        return $number;
    }

    public static function kickoff(string $value, array $options): \DateTimeImmutable
    {
        $format = $options['date_format'] ?? 'd/m/Y H:i';
        if (!in_array($format, ['d/m/Y H:i', 'd/m/Y H:i:s', 'd-m-Y H:i', 'Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d\\TH:i:sP', 'H:i'], true)) {
            throw new \InvalidArgumentException('Format de date non reconnu.');
        }
        $tz = $options['timezone'] ?? 'Europe/Paris';
        if (!in_array($tz, \DateTimeZone::listIdentifiers(), true) && $tz !== 'UTC') { throw new \InvalidArgumentException('Fuseau horaire non reconnu.'); }
        if ($format === 'H:i') { $value = ($options['match_date'] ?? '') . ' ' . $value; $format = 'Y-m-d H:i'; }
        $date = \DateTimeImmutable::createFromFormat('!' . $format, $value, new \DateTimeZone($tz));
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $date->format($format) !== $value) {
            throw new \InvalidArgumentException('Date invalide ou format incorrect : ' . $value . '.');
        }
        return $date->setTimezone(new \DateTimeZone('UTC'));
    }

    private static function identity(array $row, array $map, array $options): array
    {
        $home = self::cell($row, $map, 'home');
        $away = self::cell($row, $map, 'away');
        $rawDate = self::cell($row, $map, 'kickoff');
        if (!$home || !$away || !$rawDate || $home === $away || strlen($home) > 180 || strlen($away) > 180) {
            throw new \InvalidArgumentException('Équipes ou horaire manquants / invalides.');
        }
        $date = self::kickoff($rawDate, $options);
        $normalize = static function ($s) { return strtolower(preg_replace('/\s+/u', ' ', trim($s))); };
        $join = hash('sha256', $date->format('c') . '|' . $normalize($home) . '|' . $normalize($away));
        return ['home' => $home, 'away' => $away, 'kickoff' => $date->format('c'), 'league' => self::cell($row, $map, 'league') ?: 'Compétition non renseignée', 'key' => $join];
    }

    public static function under(float $lambda, int $maxGoals): float
    {
        if ($lambda < 0 || !is_finite($lambda)) { throw new \InvalidArgumentException('Intensité de buts invalide.'); }
        $term = exp(-$lambda); $sum = $term;
        for ($k = 1; $k <= $maxGoals; $k++) { $term *= $lambda / $k; $sum += $term; }
        return max(0.0, min(1.0, $sum));
    }

    public static function probabilities(float $home, float $away, string $period): array
    {
        $out = [];
        foreach (self::markets() as $id => $market) {
            if ($market['period'] !== $period) { continue; }
            if ($market['scope'] === 'btts') {
                $yes = (1 - exp(-$home)) * (1 - exp(-$away));
                $p = $market['type'] === 'yes' ? $yes : 1 - $yes;
            } else {
                $lambda = $market['scope'] === 'total' ? $home + $away : ($market['scope'] === 'home' ? $home : $away);
                $under = self::under($lambda, (int)floor($market['line']));
                $p = $market['type'] === 'under' ? $under : 1 - $under;
            }
            $out[$id] = $market + ['id' => $id, 'probability' => $p, 'odds' => null, 'ev' => null];
        }
        return $out;
    }

    public static function analyze(array $tables, array $maps, array $options, ?\DateTimeImmutable $now = null): array
    {
        $now = $now ?: new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $known = self::markets();
        $allowed = $options['markets'] ?? array_keys($known);
        if (!is_array($allowed) || !$allowed || array_diff($allowed, array_keys($known))) { throw new \InvalidArgumentException('Sélectionnez au moins un marché valide.'); }
        $minOdds = self::number((string)($options['min_odds'] ?? ''), 'cote minimale', 1.01, 1000);
        $sampleDefault = self::number((string)($options['sample_default'] ?? ''), 'taille de l’échantillon', 1, 10000);
        if ($sampleDefault !== null && floor($sampleDefault) !== $sampleDefault) { throw new \InvalidArgumentException('Le nombre de matchs doit être entier.'); }
        $errors = []; $matches = []; $oddsIndex = []; $duplicates = [];
        if (isset($tables['odds'])) {
            foreach ($tables['odds']['rows'] as $i => $row) {
                try {
                    $ident = self::identity($row, $maps['odds'] ?? [], $options);
                    $key = $ident['key'];
                    if (isset($oddsIndex[$key])) { $duplicates[$key] = true; }
                    $oddsIndex[$key] = ['row' => $row, 'identity' => $ident];
                } catch (\InvalidArgumentException $e) { $errors[] = ['file' => 'cotes', 'row' => $i + 1, 'message' => $e->getMessage()]; }
            }
        }
        $seen = []; $badKeys = [];
        foreach ($tables['stats']['rows'] as $i => $row) {
            try {
                $map = $maps['stats'] ?? [];
                $match = self::identity($row, $map, $options);
                $key = $match['key'];
                if (isset($seen[$key])) { $badKeys[$key] = true; throw new \InvalidArgumentException('Match en double : toutes ses lignes sont écartées.'); }
                $seen[$key] = true;
                if (new \DateTimeImmutable($match['kickoff']) <= $now) { throw new \InvalidArgumentException('Match déjà commencé : exclu du prématch.'); }
                $status = self::cell($row, $map, 'status');
                if ($status !== null && !in_array(strtolower($status), ['ns', 'not started', 'scheduled', 'à venir', 'a venir'], true)) {
                    throw new \InvalidArgumentException('Statut non prématch ou non reconnu : ' . $status . '.');
                }
                $stats = []; $warnings = [];
                if ($status === null) { $warnings[] = 'Statut non fourni : seul l’horaire a été contrôlé.'; }
                foreach (['home_n', 'away_n'] as $field) {
                    $n = self::number(self::cell($row, $map, $field), $field, 1, 10000);
                    $n = $n === null ? $sampleDefault : $n;
                    if ($n === null || floor($n) !== $n) { throw new \InvalidArgumentException('Nombre de matchs manquant ou non entier.'); }
                    $stats[$field] = (int)$n;
                    if ($n < 20) { $warnings[] = ($field === 'home_n' ? 'Domicile' : 'Extérieur') . ' : échantillon limité à ' . (int)$n . ' matchs.'; }
                }
                $all = []; $lambdas = [];
                foreach (['ft' => '', 'h1' => 'h1_', 'h2' => 'h2_'] as $period => $prefix) {
                    $v = [];
                    foreach (['home_gf', 'home_ga', 'away_gf', 'away_ga'] as $field) {
                        $v[$field] = self::number(self::cell($row, $map, $prefix . $field), $prefix . $field);
                        $stats[$prefix . $field] = $v[$field];
                    }
                    $count = count(array_filter($v, static function ($x) { return $x !== null; }));
                    if ($count !== 4) {
                        if ($period === 'ft') { throw new \InvalidArgumentException('Les quatre moyennes de buts du match sont nécessaires.'); }
                        if ($count > 0) { $warnings[] = 'Statistiques ' . $period . ' incomplètes : période ignorée.'; }
                        continue;
                    }
                    $home = ($v['home_gf'] + $v['away_ga']) / 2;
                    $away = ($v['away_gf'] + $v['home_ga']) / 2;
                    $lambdas[$period] = ['home' => $home, 'away' => $away];
                    $all += self::probabilities($home, $away, $period);
                }
                foreach (['home_gf', 'home_ga', 'away_gf', 'away_ga'] as $field) {
                    foreach (['h1_', 'h2_'] as $prefix) {
                        if ($stats[$prefix . $field] !== null && $stats[$prefix . $field] > $stats[$field] + 0.15) { throw new \InvalidArgumentException('Moyenne de mi-temps supérieure au match entier : vérifiez le mapping.'); }
                    }
                    if ($stats['h1_' . $field] !== null && $stats['h2_' . $field] !== null && abs($stats['h1_' . $field] + $stats['h2_' . $field] - $stats[$field]) > 0.2) {
                        throw new \InvalidArgumentException('Les deux mi-temps ne correspondent pas au total : vérifiez les échantillons.');
                    }
                }
                $oddsRow = null;
                if (isset($duplicates[$key])) { $warnings[] = 'Plusieurs lignes de cotes : aucune cote associée.'; }
                elseif (isset($oddsIndex[$key])) {
                    $oi = $oddsIndex[$key]['identity'];
                    if ($oi['league'] !== 'Compétition non renseignée' && $match['league'] !== 'Compétition non renseignée' && strtolower($oi['league']) !== strtolower($match['league'])) {
                        $warnings[] = 'Compétitions différentes : aucune cote associée.';
                    } else { $oddsRow = $oddsIndex[$key]['row']; }
                } elseif (isset($tables['odds'])) { $warnings[] = 'Aucune correspondance exacte dans les cotes.'; }
                $eligible = [];
                foreach ($all as $id => &$candidate) {
                    $candidate['eligible'] = in_array($id, $allowed, true);
                    $oddsMap = $oddsRow === null ? $map : ($maps['odds'] ?? []);
                    $sourceRow = $oddsRow === null ? $row : $oddsRow;
                    try {
                        $candidate['odds'] = self::number(self::cell($sourceRow, $oddsMap, $id), $candidate['label'], 1.001, 1000);
                    } catch (\InvalidArgumentException $e) { $warnings[] = $e->getMessage(); }
                    if ($candidate['odds'] !== null) { $candidate['ev'] = $candidate['probability'] * $candidate['odds'] - 1; }
                    if ($minOdds !== null && ($candidate['odds'] === null || $candidate['odds'] < $minOdds)) { $candidate['eligible'] = false; }
                    if (!empty($options['require_odds']) && $candidate['odds'] === null) { $candidate['eligible'] = false; }
                    if ($candidate['eligible']) { $eligible[] = $candidate; }
                }
                unset($candidate);
                usort($eligible, static function ($a, $b) { return ($b['probability'] <=> $a['probability']) ?: strcmp($a['id'], $b['id']); });
                $match['stats'] = $stats; $match['lambdas'] = $lambdas; $match['candidates'] = array_values($all);
                $match['pick'] = $eligible[0] ?? null;
                $match['warnings'] = array_values(array_unique($warnings));
                $match['context_status'] = 'not_checked';
                $match['row'] = $i + 1;
                $matches[$key] = $match;
            } catch (\InvalidArgumentException $e) { $errors[] = ['file' => 'statistiques', 'row' => $i + 1, 'message' => $e->getMessage()]; }
        }
        foreach ($badKeys as $key => $_) { unset($matches[$key]); }
        $matches = array_values($matches);
        usort($matches, static function ($a, $b) { return (($b['pick']['probability'] ?? -1) <=> ($a['pick']['probability'] ?? -1)) ?: strcmp($a['key'], $b['key']); });
        return ['version' => self::VERSION, 'generated_at' => $now->format('c'), 'matches' => $matches, 'errors' => $errors, 'options' => $options, 'calibration' => 'experimental', 'input_count' => count($tables['stats']['rows'])];
    }

    public static function settle(array $pick, int $home, int $away): bool
    {
        if ($home < 0 || $away < 0 || $home > 30 || $away > 30) { throw new \InvalidArgumentException('Score invalide.'); }
        if ($pick['scope'] === 'btts') { return $pick['type'] === 'yes' ? ($home > 0 && $away > 0) : ($home === 0 || $away === 0); }
        $goals = $pick['scope'] === 'home' ? $home : ($pick['scope'] === 'away' ? $away : $home + $away);
        return $pick['type'] === 'over' ? $goals > $pick['line'] : $goals < $pick['line'];
    }
}
