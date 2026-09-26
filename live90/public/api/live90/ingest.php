<?php
declare(strict_types=1);
require __DIR__.'/core.php';
if (($_SERVER['REQUEST_METHOD']??'')!=='POST') reply90(['error'=>'POST uniquement'],405);
$env=config90(); $secret=$env['SE_LIVE_TOKEN']??'';
if (!$secret || !hash_equals((string)$secret,(string)($_SERVER['HTTP_X_SE_TOKEN']??''))) reply90(['error'=>'Authentification requise'],401);
$x=body90();
if (($x['schema']??null)!==2 || !is_array($x['rows']??null) || count($x['rows'])>1000 || !is_string($x['cycle_id']??null) || !preg_match('/^[a-zA-Z0-9_-]{10,100}$/',$x['cycle_id'])) reply90(['error'=>'Format de collecte invalide'],400);
$ts=is_string($x['collected_at']??null)?strtotime($x['collected_at']):false;
if ($ts===false || abs(time()-$ts)>180) reply90(['error'=>'Horloge décalée ou relevé périmé'],422);
$now=gmdate('Y-m-d\TH:i:s\Z');
try {
 $db=db90(); $db->beginTransaction();
 $s=$db->prepare('INSERT OR IGNORE INTO cycles VALUES(?,?,?,?)'); $s->execute([$x['cycle_id'],$x['collected_at'],$now,json_encode($x)]);
 if (!$s->rowCount()) {$db->commit(); reply90(['ok'=>true,'duplicate'=>true,'inserted'=>0]);}
 $s=$db->prepare('INSERT INTO samples(cycle_id,match_id,received_at,data) VALUES(?,?,?,?)'); $n=0;
 foreach($x['rows'] as $r){
   if(!is_array($r) || !preg_match('/^\d{1,20}$/',(string)($r['packball_id']??''))) continue;
   $s->execute([$x['cycle_id'],(string)$r['packball_id'],$now,json_encode($r,JSON_UNESCAPED_UNICODE)]); $n++;
 }
 $db->commit();
 // Binding does not depend on the admin browser being open.
 try {
  $proc=proc_open(['/usr/bin/python3',dirname(__DIR__,3).'/live90/server/scenarios.py',$env['SE90_DB']??'/var/lib/stratedge/live90.sqlite','--resolve'],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','a']],$pipes);
  if(is_resource($proc)&&proc_close($proc)!==0)error_log('Live90: association en attente, nouvelle tentative au prochain relevé');
 }catch(Throwable $e){error_log('Live90: association indisponible');}
 reply90(['ok'=>true,'inserted'=>$n,'received_at'=>$now]);
} catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack(); error_log('Live90 ingest: '.$e->getMessage()); reply90(['error'=>'Stockage indisponible'],500);}
