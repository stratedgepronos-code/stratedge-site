<?php
declare(strict_types=1);
namespace StratEdgeLab;

final class ResultsImport
{
    public static function apply(Store $store, int $owner, string $csv, string $filename, ?\DateTimeImmutable $now = null): array
    {
        $table = Engine::csv($csv, 'auto', true);
        if ($table['headers'] !== PackBall::headers()) { throw new \InvalidArgumentException('Le format attendu est le même export PackBall à 46 colonnes.'); }
        $now = $now ?? new \DateTimeImmutable('now');
        $index = []; $duplicates = []; $messages = []; $report = ['recorded' => 0, 'existing' => 0, 'conflict' => 0, 'messages' => []];
        foreach ($table['rows'] as $i => $row) {
            try {
                $identity = Engine::identity($row, PackBall::mapping(), ['date_format' => 'd-m-Y H:i', 'timezone' => 'Europe/Paris']);
                $key = $identity['key'];
                if (isset($index[$key])) { $duplicates[$key] = true; }
                $index[$key] = ['identity' => $identity, 'row' => $row, 'line' => $i + 1];
            } catch (\InvalidArgumentException $e) { $messages[] = 'Ligne ' . ($i + 1) . ' : ' . $e->getMessage(); }
        }
        $targets = [];
        foreach ($store->recent($owner, 'analysis', 100) as $summary) {
            $run = $store->get($summary['id'], $owner);
            foreach ($run['data']['analysis']['matches'] as $match) {
                if ($match['pick']) { $targets[$match['key']][] = ['id' => $run['id'], 'match' => $match]; }
            }
        }
        foreach ($index as $key => $entry) {
            $row = $entry['row']; $label = $entry['identity']['home'] . ' — ' . $entry['identity']['away'];
            if (isset($duplicates[$key])) { $messages[] = $label . ' : lignes en double, aucune validation.'; continue; }
            if (strtoupper(trim($row[4])) !== 'FT') { $messages[] = $label . ' : statut non FT, laissé en attente (annulation à vérifier manuellement).'; continue; }
            if ($now < (new \DateTimeImmutable($entry['identity']['kickoff']))->modify('+90 minutes')) { $messages[] = $label . ' : horaire incompatible avec un match terminé.'; continue; }
            $scores = [];
            foreach ([6, 7] as $col) {
                $v = trim($row[$col]);
                $scores[] = preg_match('/^\d{1,2}$/D', $v) && (int)$v <= 30 ? (int)$v : null;
            }
            if (in_array(null, $scores, true)) { $messages[] = $label . ' : score final absent ou invalide.'; continue; }
            if (empty($targets[$key])) { $messages[] = $label . ' : aucun pari correspondant dans les 100 dernières analyses.'; continue; }
            foreach ($targets[$key] as $target) {
                $match = $target['match'];
                if (trim($match['league']) !== trim($entry['identity']['league'])) { $messages[] = $label . ' : compétition différente, vérification manuelle nécessaire.'; continue; }
                if ($match['pick']['period'] !== 'ft') { $messages[] = $label . ' : ancien pari par période, à régler dans sa fiche.'; continue; }
                $data = ['outcome' => Engine::settle($match['pick'], $scores[0], $scores[1]) ? 'won' : 'lost',
                    'home' => $scores[0], 'away' => $scores[1], 'period' => 'ft', 'source' => '',
                    'note' => 'Score importé du CSV PackBall (FT).', 'import' => ['filename' => substr(basename($filename), 0, 180), 'sha256' => hash('sha256', $csv), 'line' => $entry['line']]];
                $status = $store->resultIfAbsent($target['id'], $key, $data, $owner);
                $report[$status]++;
                if ($status === 'conflict') { $messages[] = $label . ' : résultat déjà enregistré différent ; conservé, à contrôler dans sa fiche.'; }
            }
        }
        $report['messages'] = $messages;
        return $report;
    }
}
