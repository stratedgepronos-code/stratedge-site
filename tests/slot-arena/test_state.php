<?php
declare(strict_types=1);
require __DIR__.'/../../public_html/tournoi-slots/state.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function rejected(array $state): bool { try { arena_validate($state); return false; } catch (InvalidArgumentException $e) { return true; } }
function match_row($a, $b): array { return ['players'=>[$a,$b],'scores'=>['',''],'winner'=>null,'bye'=>false]; }
for ($n=1; $n<=64; $n++) {
    $state=['schema'=>'slot-arena.v1','id'=>'test-'.$n,'title'=>'Test','players'=>['A','B'],'slots'=>[],'rounds'=>[],'order'=>'list','updatedAt'=>0];
    for ($i=0;$i<$n*2;$i++) $state['slots'][]=['id'=>'s'.$i,'name'=>'Slot '.$i,'owner'=>$i<$n?0:1];
    $first=[];for($i=0;$i<$n;$i++)$first[]=match_row('s'.$i,'s'.($n+$i));$state['rounds'][]=$first;
    while(count(end($state['rounds']))>1){$last=end($state['rounds']);$next=[];for($i=0;$i<count($last);$i+=2){$m=match_row(null,null);$m['bye']=!isset($last[$i+1]);$next[]=$m;}$state['rounds'][]=$next;}
    arena_validate($state);$games=0;
    for($r=0;$r<count($state['rounds']);$r++){
        foreach($state['rounds'][$r] as $i=>&$m){if($r){$m['players']=[$state['rounds'][$r-1][$i*2]['winner'],$m['bye']?null:$state['rounds'][$r-1][$i*2+1]['winner']];}if($m['bye'])$m['winner']=$m['players'][0];else{$m['scores']=['12,5','0'];$m['winner']=$m['players'][0];$games++;}}unset($m);
    }
    $clean=arena_validate($state);check($games===$n*2-1,'Every slot but one eliminated');check(arena_champion($clean)==='s0','Champion');
    $event=arena_event(null,$clean,null);check($event!==null&&$event['champion']==='s0','Finale event');check(arena_event($clean,$clean,$event)===$event,'Event not repeated for same winner');
    $invalid=$clean;$invalid['rounds'][0][0]['scores']=['0','100'];check(rejected($invalid),'Reject false winner');
    $invalid=$clean;$invalid['slots'][0]['owner']=2;check(rejected($invalid),'Reject owner');
}
$empty=['schema'=>'slot-arena.v1','id'=>'empty','title'=>'','players'=>['A','B'],'slots'=>[],'rounds'=>[],'order'=>'random'];
check(arena_champion(arena_validate($empty))===null,'No early champion');check(arena_event(null,$empty,null)===null,'No early animation');
check(arena_score('0')===0.0 && arena_score('1,25')===1.25 && arena_score('-1')===null,'Scores');
echo "Slot Arena: 64 formats, validation, elimination and finale gates OK\n";
