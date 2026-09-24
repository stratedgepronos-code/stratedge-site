<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/includes/auth.php';
requireSuperAdmin();
require_once dirname(__DIR__,3).'/api/live90/core.php';
if(session_status()!==PHP_SESSION_ACTIVE) session_start();
try {
$db=db90();
if(($_SERVER['REQUEST_METHOD']??'GET')==='GET') {
 $matches=[];
 foreach($db->query('SELECT s.* FROM samples s JOIN (SELECT match_id,MAX(id) id FROM samples GROUP BY match_id) l ON s.id=l.id ORDER BY s.id DESC LIMIT 500') as $s){
  $r=json_decode($s['data'],true); $r['received_at']=$s['received_at']; $r['sample_id']=(int)$s['id'];
  $p=$db->prepare('SELECT data,recorded_at FROM prematch WHERE match_id=? ORDER BY id DESC LIMIT 1'); $p->execute([$s['match_id']]); $b=$p->fetch(); $r['prematch']=$b?json_decode($b['data'],true):null;
  $p=$db->prepare('SELECT * FROM decisions WHERE match_id=?');$p->execute([$s['match_id']]);$r['decision']=$p->fetch()?:null;$matches[]=$r;
 }
 $signals=$db->query('SELECT * FROM signals ORDER BY id DESC LIMIT 200')->fetchAll();
 foreach($signals as &$s)$s['context']=json_decode($s['context'],true); unset($s);
 $cycle=$db->query('SELECT received_at FROM cycles ORDER BY rowid DESC LIMIT 1')->fetch();
 reply90(['matches'=>$matches,'signals'=>$signals,'last_cycle'=>$cycle['received_at']??null,'server_time'=>gmdate('c')]);
}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')reply90(['error'=>'Méthode non autorisée'],405);
if(!hash_equals((string)($_SESSION['live90_csrf']??''),(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')) || empty($_SESSION['live90_csrf']))reply90(['error'=>'Session expirée, recharge la page'],403);
$x=body90();
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
if(($x['action']??'')==='settle') {
 $id=filter_var($x['id']??null,FILTER_VALIDATE_INT);$h=$x['score_h']??null;$a=$x['score_a']??null;$note=trim((string)($x['note']??''));
 if(!$id||!is_int($h)||!is_int($a)||min($h,$a)<0||max($h,$a)>30||strlen($note)<5||strlen($note)>500)reply90(['error'=>'Score final et source de vérification requis'],422);
 $db->beginTransaction();$q=$db->prepare('SELECT * FROM signals WHERE id=?');$q->execute([$id]);$s=$q->fetch();
 if(!$s){$db->rollBack();reply90(['error'=>'Signal absent'],404);}
 $out=$h+$a>(float)$s['line']?'won':'lost';$now=gmdate('c');
 $q=$db->prepare('INSERT INTO settlements(signal_id,created_at,score_h,score_a,note) VALUES(?,?,?,?,?)');$q->execute([$id,$now,$h,$a,$note]);
 $q=$db->prepare('UPDATE signals SET outcome=?,settled_at=? WHERE id=?');$q->execute([$out,$now,$id]);$db->commit();reply90(['ok'=>true]);
}
reply90(['error'=>'Action inconnue'],400);
} catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();error_log('Live90 admin: '.$e->getMessage());reply90(['error'=>'Service indisponible : consulter le journal serveur'],500);}
