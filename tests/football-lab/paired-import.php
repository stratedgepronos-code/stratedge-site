<?php
use StratEdgeLab\PackBall as PairPackBall;
$pairFiles = [
    ['name'=>'GPT.csv','csv'=>file_get_contents(__DIR__.'/pair-gpt-33.csv')],
    ['name'=>'GPT-2.csv','csv'=>file_get_contents(__DIR__.'/pair-gpt2-28.csv')],
];
$pairNow = new DateTimeImmutable('2026-09-14T00:00:00Z');
$paired = PairPackBall::preparePair($pairFiles,$pairNow);
check($paired['import']['rows']===6 && count($paired['analysis']['matches'])===5, 'Six paired fixtures produce five upcoming matches and exclude postponed match');
$byHome = array_column($paired['analysis']['matches'],null,'home');
$elche = $byHome['Elche']; $markets = array_column($elche['candidates'],null,'id');
near($markets['ft_over_15']['odds'],1.08,'Pair maps over 1.5 correctly');
near($markets['ft_under_15']['odds'],6.8,'Pair maps under 1.5 correctly');
near($markets['ft_btts_yes']['odds'],1.65,'Pair keeps BTTS yes price');
near($markets['ft_btts_no']['odds'],2.15,'Pair keeps BTTS no price');
near($elche['packball']['gpt_features']['home_shots'],9.8,'Combined shot pair split correctly');
check($elche['packball']['gpt2']['second_half_over05_percent']==='90', 'Second-half frequency retained without inventing halftime scoring means');
check(count($paired['sources'])===2,'Both original tables retained for replay');
$reversed = PairPackBall::preparePair(array_reverse($pairFiles),$pairNow);
check(array_column($reversed['analysis']['matches'],'key')===array_column($paired['analysis']['matches'],'key'),'File ordering is irrelevant');
rejects(static function() use($pairFiles,$pairNow){PairPackBall::preparePair([$pairFiles[0],$pairFiles[0]],$pairNow);},'Two identical profiles rejected');
$badPair=$pairFiles; $badPair[1]['csv']=str_replace('"1.08"','"1.09"',$badPair[1]['csv']);
rejects(static function() use($badPair,$pairNow){PairPackBall::preparePair($badPair,$pairNow);},'Conflicting odds do not silently select a source');
$badPair=$pairFiles; $badPair[1]['csv']=str_replace('Real Madrid','Other team',$badPair[1]['csv']);
rejects(static function() use($badPair,$pairNow){PairPackBall::preparePair($badPair,$pairNow);},'Mismatched fixture sets rejected');
check(PairPackBall::profile(StratEdgeLab\Engine::csv($pairFiles[0]['csv'],'auto',true)['headers'])===PairPackBall::PROFILE_33,'33-column format recognized for results imports');
