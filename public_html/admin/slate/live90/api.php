<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/includes/auth.php';
requireSuperAdmin();
require_once dirname(__DIR__,3).'/api/live90/core.php';
if(session_status()!==PHP_SESSION_ACTIVE) session_start();
function context90(PDO $db,array $r): array {
 $q=$db->prepare('SELECT id,prematch_id,created_at,status,data,sources FROM context_reports WHERE match_id=? ORDER BY id DESC LIMIT 1');$q->execute([$r['packball_id']]);$ctx=$q->fetch();
 if($ctx){$ctx['data']=json_decode($ctx['data'],true);$ctx['sources']=json_decode($ctx['sources'],true);}$r['context']=$ctx?:null;
 $q=$db->prepare('SELECT kind,state,created_at,error FROM context_jobs WHERE match_id=? ORDER BY id DESC LIMIT 1');$q->execute([$r['packball_id']]);$r['context_job']=$q->fetch()?:null;
 $q=$db->prepare('SELECT id,generated_at,imported_at,data FROM playbooks WHERE match_id=? ORDER BY id DESC LIMIT 1');$q->execute([$r['packball_id']]);$book=$q->fetch();if($book)$book['data']=json_decode($book['data'],true);$r['playbook']=$book?:null;
 return $r;
}
try {
$db=db90();
$db->exec('CREATE TABLE IF NOT EXISTS preparation(id INTEGER PRIMARY KEY CHECK(id=1), data TEXT NOT NULL)');
if(($_SERVER['REQUEST_METHOD']??'GET')==='GET') {
 $matches=[];
 foreach($db->query('SELECT s.* FROM samples s JOIN (SELECT match_id,MAX(id) id FROM samples GROUP BY match_id) l ON s.id=l.id ORDER BY s.id DESC LIMIT 500') as $s){
  $r=json_decode($s['data'],true); $r['received_at']=$s['received_at']; $r['sample_id']=(int)$s['id'];
  $p=$db->prepare('SELECT data,recorded_at FROM prematch WHERE match_id=? ORDER BY id DESC LIMIT 1'); $p->execute([$s['match_id']]); $b=$p->fetch(); $r['prematch']=$b?json_decode($b['data'],true):null;
  $p=$db->prepare('SELECT * FROM decisions WHERE match_id=?');$p->execute([$s['match_id']]);$r['decision']=$p->fetch()?:null;$matches[]=context90($db,$r);
 }
 foreach($db->query('SELECT p.* FROM prematch p JOIN (SELECT match_id,MAX(id) id FROM prematch GROUP BY match_id) l ON p.id=l.id WHERE NOT EXISTS(SELECT 1 FROM samples s WHERE s.match_id=p.match_id) ORDER BY p.id DESC LIMIT 200') as $p){
  $b=json_decode($p['data'],true);$matches[]=context90($db,['packball_id'=>$p['match_id'],'home'=>$b['home'],'away'=>$b['away'],'league'=>'Avant-match importé','state'=>'NS','minute'=>null,'score'=>null,'kickoff_ts'=>$p['kickoff'],'received_at'=>null,'prematch'=>$b,'decision'=>null]);
 }
 $signals=$db->query('SELECT * FROM signals ORDER BY id DESC LIMIT 200')->fetchAll();
 foreach($signals as &$s)$s['context']=json_decode($s['context'],true); unset($s);
 $cycle=$db->query('SELECT received_at FROM cycles ORDER BY rowid DESC LIMIT 1')->fetch();
 $ai=['enabled'=>false,'calls_today'=>0,'limit'=>0,'mode'=>'local_scenarios'];
 reply90(['ai'=>$ai,'matches'=>$matches,'signals'=>$signals,'last_cycle'=>$cycle['received_at']??null,'preparation'=>json_decode($db->query('SELECT data FROM preparation WHERE id=1')->fetchColumn()?:'null',true),'pending_scenarios'=>$db->query("SELECT state,data FROM pending_playbooks WHERE state!='linked'")->fetchAll(),'server_time'=>gmdate('c')]);
}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')reply90(['error'=>'Méthode non autorisée'],405);
if(!hash_equals((string)($_SESSION['live90_csrf']??''),(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')) || empty($_SESSION['live90_csrf']))reply90(['error'=>'Session expirée, recharge la page'],403);
$x=body90();
if(($x['action']??'')==='preparation') {
 $b=$x['bundle']??null;
 if(!is_array($b)||($b['schema']??'')!=='stratedge.analysis.v1'||!is_array($b['matches']??null)||count($b['matches'])<1||count($b['matches'])>200)reply90(['error'=>'Dossier de préparation invalide'],422);
 foreach($b['matches'] as $m)if(!is_array($m)||!is_string($m['home']??null)||!is_string($m['away']??null)||!is_array($m['prematch']??null))reply90(['error'=>'Match de préparation invalide'],422);
 $q=$db->prepare('INSERT OR REPLACE INTO preparation(id,data) VALUES(1,?)');$q->execute([json_encode($b,JSON_THROW_ON_ERROR)]);reply90(['ok'=>true,'imported'=>count($b['matches'])]);
}
if(($x['action']??'')==='prematch') {
 if(!is_array($x['rows']??null)||count($x['rows'])>1000)reply90(['error'=>'Import invalide'],422);
 $accepted=[];$errors=[];$now=gmdate('Y-m-d\TH:i:s\Z');
 foreach($x['rows'] as $k=>$r){
  if(!is_array($r)){$errors[]='Ligne '.($k+1).' invalide';continue;}
  $id=(string)($r['match_id']??'');$kick=is_string($r['kickoff']??null)?strtotime($r['kickoff']):false;
  $valid=preg_match('/^\d{1,20}$/',$id)&&$kick!==false&&$kick>time()&&$kick<time()+86400*14;
  foreach(['home','away'] as $name)$valid=$valid&&is_string($r[$name]??null)&&strlen(trim($r[$name]))>0&&strlen($r[$name])<200;
  foreach(['n_h','n_a','gf_h','gf_a','ga_h','ga_a','shots_h','shots_a','sot_h','sot_a'] as $f){$v=number90($r[$f]??null);$valid=$valid&&$v!==null&&$v>=0&&$v<=100;}
  $valid=$valid&&($r['n_h']??0)>=1&&($r['n_a']??0)>=1&&floor($r['n_h']??0)===floatval($r['n_h']??0)&&floor($r['n_a']??0)===floatval($r['n_a']??0);
  $valid=$valid&&($r['sot_h']??0)<=($r['shots_h']??0)&&($r['sot_a']??0)<=($r['shots_a']??0);
  foreach(['odds_o25','odds_u25'] as $f){if(isset($r[$f])){$v=number90($r[$f]);$valid=$valid&&$v!==null&&$v>1&&$v<100;}}
  if(!$valid){$errors[]='Ligne '.($k+1).' : ID, date future ou statistiques invalides';continue;}
  $r['kickoff']=gmdate('Y-m-d\TH:i:s\Z',$kick);$r['recorded_at']=$now;$r['source']='Packball · import manuel';$accepted[]=$r;
 }
 if($errors)reply90(['error'=>'Import non enregistré','details'=>$errors],422);
 $db->beginTransaction();$q=$db->prepare('INSERT INTO prematch(match_id,recorded_at,kickoff,data) VALUES(?,?,?,?)');
 foreach($accepted as $r)$q->execute([$r['match_id'],$now,$r['kickoff'],json_encode($r)]);
 $db->commit();reply90(['ok'=>true,'imported'=>count($accepted)]);
}
if(($x['action']??'')==='context')reply90(['error'=>'Recherche API désactivée : importe un dossier de scénarios'],410);
if(($x['action']??'')==='scenarios') {
 if(!is_array($x['bundle']??null))reply90(['error'=>'Dossier JSON attendu'],422);
 $env=config90();$path=$env['SE90_DB']??'/var/lib/stratedge/live90.sqlite';
 // Fixed executable and argument array: uploaded text is stdin only, never shell code.
 $proc=proc_open(['/usr/bin/python3',dirname(__DIR__,4).'/live90/server/scenarios.py',$path],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','/dev/null','a']],$pipes);
 if(!is_resource($proc))reply90(['error'=>'Validateur indisponible'],503);
 $payload=json_encode($x['bundle'],JSON_THROW_ON_ERROR);$offset=0;
 while($offset<strlen($payload)){ $written=fwrite($pipes[0],substr($payload,$offset));if($written===false||$written===0)break;$offset+=$written; }
 fclose($pipes[0]);$out=stream_get_contents($pipes[1],65536);fclose($pipes[1]);$code=proc_close($proc);$result=json_decode($out,true);
 if(!is_array($result))reply90(['error'=>'Validation indisponible : vérifier Python et proc_open'],503);
 reply90($result,$code===0?200:($code===2?422:503));
}
if(($x['action']??'')==='settle') {
 $id=filter_var($x['id']??null,FILTER_VALIDATE_INT);$h=$x['score_h']??null;$a=$x['score_a']??null;$note=trim((string)($x['note']??''));
 if(!$id||!is_int($h)||!is_int($a)||min($h,$a)<0||max($h,$a)>30||strlen($note)<5||strlen($note)>500)reply90(['error'=>'Score final et source de vérification requis'],422);
 $db->beginTransaction();$q=$db->prepare('SELECT * FROM signals WHERE id=?');$q->execute([$id]);$s=$q->fetch();
 if(!$s){$db->rollBack();reply90(['error'=>'Signal absent'],404);}
 $ctx=json_decode($s['context'],true);$period=$ctx['period']??'FT';
 if(($x['period']??'FT')!==$period){$db->rollBack();reply90(['error'=>'Période du score incorrecte pour ce marché'],422);}
 if($s['market']==='team_goals')$goals=($ctx['team']??'')==='h'?$h:$a;
 elseif(in_array($s['market'],['total_goals','total_goals_over_FT'],true))$goals=$h+$a;
 else{$db->rollBack();reply90(['error'=>'Marché non pris en charge pour le règlement'],422);}
 $out=$goals>(float)$s['line']?'won':'lost';$now=gmdate('c');$note='['.$period.'] '.$note;
 $q=$db->prepare('INSERT INTO settlements(signal_id,created_at,score_h,score_a,note) VALUES(?,?,?,?,?)');$q->execute([$id,$now,$h,$a,$note]);
 $q=$db->prepare('UPDATE signals SET outcome=?,settled_at=? WHERE id=?');$q->execute([$out,$now,$id]);$db->commit();reply90(['ok'=>true]);
}
reply90(['error'=>'Action inconnue'],400);
} catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();error_log('Live90 admin: '.$e->getMessage());reply90(['error'=>'Service indisponible : consulter le journal serveur'],500);}
