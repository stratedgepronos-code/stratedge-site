<?php
declare(strict_types=1);

function arena_text($value, int $max): bool {
    return is_string($value) && trim($value) !== '' && strlen($value) <= $max;
}
function arena_score($value): ?float {
    if (!is_string($value) || !preg_match('/^\d+(?:[.,]\d+)?$/D', trim($value))) return null;
    $n = (float) str_replace(',', '.', trim($value));
    return is_finite($n) && $n >= 0 && $n <= 1.0e12 ? $n : null;
}
function arena_layout(int $count): array {
    if ($count === 1) return [[1,0]];
    $a=(int)ceil($count/2); $b=(int)floor($count/2); $layout=[[$a,$b]];
    while ($a>1 || $b>1) { $a=(int)ceil($a/2); $b=(int)ceil($b/2); $layout[]=[$a,$b]; }
    $layout[]=[1,0]; return $layout;
}
function arena_sources(array $rounds, int $r, int $i, bool $balanced): array {
    if (!$balanced) return [$i*2, $i*2+1<count($rounds[$r-1]) ? $i*2+1 : null];
    if ($r===count($rounds)-1) return [0,1];
    $layout=arena_layout(count($rounds[0])); $prev=$layout[$r-1]; $current=$layout[$r];
    $right=$i >= $current[0]; $offset=$right ? $prev[0] : 0;
    $index=$offset+2*($right ? $i-$current[0] : $i); $end=$right ? $prev[0]+$prev[1] : $prev[0];
    return [$index, $index+1<$end ? $index+1 : null];
}
function arena_balance(?array $s): ?array {
    if (!$s || ($s['bracket'] ?? '')==='balanced.v1' || empty($s['rounds'])) return $s;
    foreach($s['rounds'][0] as $m) if ($m['bye']) return $s;
    foreach(array_slice($s['rounds'],1) as $round) foreach($round as $m) if (($m['winner'] && !$m['bye']) || $m['scores'][0]!=='' || $m['scores'][1]!=='') return $s;
    $first=$s['rounds'][0]; $s['rounds']=[];
    foreach(arena_layout(count($first)) as [$a,$b]) $s['rounds'][]=array_fill(0,$a+$b,['players'=>[null,null],'scores'=>['',''],'winner'=>null,'bye'=>false]);
    $s['rounds'][0]=$first; $s['bracket']='balanced.v1';
    for($r=1;$r<count($s['rounds']);$r++) foreach($s['rounds'][$r] as $i=>&$m) {
        [$a,$b]=arena_sources($s['rounds'],$r,$i,true); $m['bye']=$b===null;
        $m['players']=[$s['rounds'][$r-1][$a]['winner'],$b===null?null:$s['rounds'][$r-1][$b]['winner']];
        $m['winner']=$m['bye']?$m['players'][0]:null;
    } unset($m);
    return $s;
}
function arena_validate($s): array {
    if (!is_array($s) || ($s['schema'] ?? '') !== 'slot-arena.v1' || !arena_text($s['id'] ?? null, 100)
        || !is_string($s['title'] ?? null) || strlen($s['title']) > 320
        || !in_array($s['order'] ?? '', ['random', 'list'], true)
        || !is_array($s['players'] ?? null) || count($s['players']) !== 2
        || !is_array($s['slots'] ?? null) || count($s['slots']) > 128
        || !is_array($s['rounds'] ?? null) || count($s['rounds']) > 7) throw new InvalidArgumentException('Format de tournoi invalide.');
    foreach ($s['players'] as $player) if (!arena_text($player, 160)) throw new InvalidArgumentException('Joueur invalide.');
    $ids = []; $slots = []; $counts = [0, 0];
    foreach ($s['slots'] as $slot) {
        if (!is_array($slot) || !arena_text($slot['id'] ?? null, 100) || isset($ids[$slot['id']])
            || !arena_text($slot['name'] ?? null, 320) || !in_array($slot['owner'] ?? null, [0, 1], true)) throw new InvalidArgumentException('Slot invalide.');
        $ids[$slot['id']] = true; $counts[$slot['owner']]++;
        $slots[] = ['id' => $slot['id'], 'name' => $slot['name'], 'owner' => $slot['owner']];
    }
    $rounds = []; $entered = [];
    $balanced=($s['bracket'] ?? '')==='balanced.v1';
    if(isset($s['bracket']) && !$balanced) throw new InvalidArgumentException('Disposition invalide.');
    $layout=$balanced && $s['rounds'] ? arena_layout(count($s['rounds'][0])) : null;
    if($layout && count($layout)!==count($s['rounds'])) throw new InvalidArgumentException('Tours incohérents.');
    if ($s['rounds'] && (count($slots) < 2 || $counts[0] !== $counts[1])) throw new InvalidArgumentException('Il faut autant de slots pour chaque joueur.');
    foreach (array_values($s['rounds']) as $r => $round) {
        if (!is_array($round) || count($round) < 1 || count($round) > 64 || ($r && (count($rounds[$r-1]) <= 1 || (!$balanced && count($round) !== (int)ceil(count($rounds[$r-1])/2))))) throw new InvalidArgumentException('Tour invalide.');
        if($balanced && count($round)!==$layout[$r][0]+$layout[$r][1]) throw new InvalidArgumentException('Moitiés incohérentes.');
        $clean = [];
        foreach (array_values($round) as $i => $m) {
            if (!is_array($m) || !is_array($m['players'] ?? null) || count($m['players']) !== 2
                || !is_array($m['scores'] ?? null) || count($m['scores']) !== 2 || !is_bool($m['bye'] ?? null)
                || !array_key_exists('winner', $m)) throw new InvalidArgumentException('Duel invalide.');
            $p = array_values($m['players']); $scores = array_values($m['scores']); $winner = $m['winner'];
            foreach ($p as $id) if ($id !== null && (!is_string($id) || !isset($ids[$id]))) throw new InvalidArgumentException('Participant inconnu.');
            foreach ($scores as $score) if (!is_string($score) || strlen($score) > 100) throw new InvalidArgumentException('Multiplicateur invalide.');
            if ($r === 0) {
                if ($p[0] === null) throw new InvalidArgumentException('Participant manquant.');
                foreach ($p as $id) if ($id !== null) { if (isset($entered[$id])) throw new InvalidArgumentException('Participant répété.'); $entered[$id] = true; }
                $bye = $p[1] === null;
            } else {
                [$a,$b]=arena_sources($s['rounds'],$r,$i,$balanced); $bye=$b===null;
                $expected = [$rounds[$r-1][$a]['winner'], $bye ? null : $rounds[$r-1][$b]['winner']];
                if ($p !== $expected) throw new InvalidArgumentException('Qualifications incohérentes.');
            }
            if ($m['bye'] !== $bye || ($winner !== null && (!is_string($winner) || !in_array($winner, $p, true)))) throw new InvalidArgumentException('Résultat invalide.');
            if ($bye) { if ($winner !== $p[0]) throw new InvalidArgumentException('Exemption invalide.'); }
            elseif ($winner !== null) {
                $a = arena_score($scores[0]); $b = arena_score($scores[1]);
                if ($p[0] === null || $p[1] === null || $a === null || $b === null || ($a > $b && $winner !== $p[0]) || ($b > $a && $winner !== $p[1])) throw new InvalidArgumentException('Le vainqueur ne correspond pas aux scores.');
            }
            $clean[] = ['players'=>$p, 'scores'=>$scores, 'winner'=>$winner, 'bye'=>$bye];
        }
        $rounds[] = $clean;
    }
    if ($rounds && (count(end($rounds)) !== 1 || count($entered) !== count($ids))) throw new InvalidArgumentException('Tableau incomplet.');
    $result = ['schema'=>'slot-arena.v1','id'=>$s['id'],'title'=>$s['title'],'players'=>array_values($s['players']), 'slots'=>$slots,'order'=>$s['order'],'rounds'=>$rounds,'updatedAt'=>(int)round(microtime(true)*1000)];
    if($balanced)$result['bracket']='balanced.v1';
    return arena_balance($result);
}
function arena_champion(?array $state): ?string {
    if (!$state || empty($state['rounds'])) return null;
    $last = $state['rounds'][count($state['rounds'])-1][0];
    return empty($last['bye']) ? ($last['winner'] ?? null) : null;
}
function arena_event(?array $before, array $after, ?array $event): ?array {
    $winner = arena_champion($after);
    if ($winner === null) return null;
    if (($before['id'] ?? null) === $after['id'] && arena_champion($before) === $winner) return $event;
    return ['id'=>bin2hex(random_bytes(12)), 'champion'=>$winner, 'tournament'=>$after['id'], 'at'=>(int)round(microtime(true)*1000)+2500];
}
function arena_ensure(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS slot_arena_live (id TINYINT UNSIGNED PRIMARY KEY, revision BIGINT UNSIGNED NOT NULL DEFAULT 0, payload MEDIUMTEXT NOT NULL, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $db->exec("INSERT IGNORE INTO slot_arena_live (id, revision, payload) VALUES (1, 0, '{\"state\":null,\"event\":null}')");
}
function arena_read(PDO $db): array {
    try { $row = $db->query('SELECT revision, payload FROM slot_arena_live WHERE id = 1')->fetch(PDO::FETCH_ASSOC); }
    catch (PDOException $e) { if ($e->getCode() === '42S02') return ['revision'=>0,'state'=>null,'event'=>null]; throw $e; }
    if (!$row) return ['revision'=>0,'state'=>null,'event'=>null];
    $payload = json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR);
    return ['revision'=>(int)$row['revision'], 'state'=>arena_balance($payload['state'] ?? null), 'event'=>$payload['event'] ?? null];
}
