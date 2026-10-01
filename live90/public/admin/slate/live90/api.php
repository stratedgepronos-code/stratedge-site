<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/includes/auth.php';
requireSuperAdmin();
require_once dirname(__DIR__,3).'/api/live90/core.php';
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
try {
 $method=$_SERVER['REQUEST_METHOD']??'GET';
 if(!in_array($method,['GET','POST'],true))reply90(['error'=>'Méthode non autorisée'],405);
 $x=['action'=>'board'];
 if($method==='POST'){
  if(empty($_SESSION['live90_csrf'])||!hash_equals((string)$_SESSION['live90_csrf'],(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))reply90(['error'=>'Session expirée : recharge la page'],403);
  $x=body90();
 }
 db90(); // Schéma historique conservé ; migration V4 additive dans Python.
 $env=config90();$path=$env['SE90_DB']??'/var/lib/stratedge/live90.sqlite';
 $pythonEnv=['PATH'=>'/usr/bin:/bin','LANG'=>'C.UTF-8','PYTHONIOENCODING'=>'utf-8','PYTHONDONTWRITEBYTECODE'=>'1'];
 foreach(['TELEGRAM_BOT_TOKEN','TELEGRAM_CHAT_ID'] as $k)if(isset($env[$k]))$pythonEnv[$k]=(string)$env[$k];
 $proc=proc_open(['/usr/bin/python3',dirname(__DIR__,4).'/live90/server/live_v4.py',$path],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','/dev/null','a']],$pipes,null,$pythonEnv);
 if(!is_resource($proc))reply90(['error'=>'Python/proc_open indisponible'],503);
 $payload=json_encode($x,JSON_THROW_ON_ERROR);$offset=0;
 while($offset<strlen($payload)){$n=fwrite($pipes[0],substr($payload,$offset));if($n===false||$n===0)break;$offset+=$n;}
 fclose($pipes[0]);$out=stream_get_contents($pipes[1]);fclose($pipes[1]);$code=proc_close($proc);$result=json_decode($out,true);
 if(!is_array($result))reply90(['error'=>'Réponse du moteur indisponible'],503);
 reply90($result,$code===0?200:($code===2?422:503));
}catch(Throwable $e){error_log('Live90 V4 admin: '.get_class($e));reply90(['error'=>'Service Live indisponible'],500);}
