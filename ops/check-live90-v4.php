<?php
declare(strict_types=1);
// Lecture seule : ne révèle jamais les clés et n’envoie aucun message.
$root=$argv[1]??'';
require $root.'/public_html/api/live90/core.php';
// Vérifier aussi les fichiers servis par PHP, même quand le moteur ne change pas.
foreach (['app.js','shell.html','style.css','packball.js','history.html','history.css','history.js'] as $asset) {
    if (!is_readable($root.'/public_html/admin/slate/live90/assets/'.$asset)) {
        throw new RuntimeException('LIVE90_ASSET_UNREADABLE: '.$asset);
    }
}
$collector = @file_get_contents($root.'/live90/live90-packball.user.js');
if (!is_string($collector) || !preg_match('/^\/\/\s*@version\s+(\d+\.\d+\.\d+)\s*$/m', $collector, $version)) {
    throw new RuntimeException('LIVE90_COLLECTOR_UNREADABLE');
}
echo 'LIVE90_COLLECTOR_AVAILABLE '.$version[1].PHP_EOL;

$cfg=config90();$db=$cfg['SE90_DB']??'/var/lib/stratedge/live90.sqlite';
$env=['PATH'=>'/usr/bin:/bin','LANG'=>'C.UTF-8','PYTHONIOENCODING'=>'utf-8','PYTHONDONTWRITEBYTECODE'=>'1'];
foreach(['TELEGRAM_BOT_TOKEN','TELEGRAM_CHAT_ID'] as $k)if(isset($cfg[$k]))$env[$k]=(string)$cfg[$k];
if (!is_readable($root.'/public_html/admin/slate/live90/historique.php')) throw new RuntimeException('LIVE90_HISTORY_UNREADABLE');
$proc=proc_open(['/usr/bin/timeout','25','/usr/bin/python3','/opt/stratedge/live90/live_v4.py',$db],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','/dev/null','a']],$pipes,null,$env);
if(!is_resource($proc))throw new RuntimeException('Historique Python indisponible');
fwrite($pipes[0],json_encode(['action'=>'daily_history','page_size'=>1]));fclose($pipes[0]);
$ledger=json_decode(stream_get_contents($pipes[1]),true);fclose($pipes[1]);$ledgerStatus=proc_close($proc);
if($ledgerStatus!==0||($ledger['schema']??'')!=='stratedge.daily-history.v1'||!isset($ledger['summary']['total']))throw new RuntimeException('LIVE90_HISTORY_ERROR');
echo 'LIVE90_DAILY_HISTORY_OK '.json_encode(['day'=>$ledger['day'],'total'=>$ledger['summary']['total'],'days'=>count($ledger['days']),'timezone'=>$ledger['timezone']],JSON_UNESCAPED_SLASHES).PHP_EOL;
$deadline=microtime(true)+70;
do {
 $proc=proc_open(['/usr/bin/timeout','--signal=TERM','--kill-after=2','25','/usr/bin/python3','/opt/stratedge/live90/live_v4.py',$db,'--diagnostic'],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['file','/dev/null','a']],$pipes,null,$env);
 if(!is_resource($proc))throw new RuntimeException('Diagnostic Python indisponible');
 $text=stream_get_contents($pipes[1]);fclose($pipes[1]);$status=proc_close($proc);$d=json_decode($text,true);
 $at=is_array($d)?strtotime((string)($d['engine']['at']??'')):false;
 if($status===0&&$at!==false&&abs(time()-$at)<=65&&($d['engine']['version']??'')==='decision5.0'&&($d['engine']['legacy_telegram_active']??true)===false&&($d['engine']['notification_mode']??'')==='confirmed_cards'&&($d['engine']['goal_policy']??'')==='goal_periods_intervals_v1'&&($d['engine']['goal_half_min_rate']??null)===0.8&&($d['engine']['goal_interval_gate']??'')==='half_only_min08_two_sot_v5'&&($d['engine']['card_momentum_policy']??'')==='card_rapid5_context_v1'){
  echo 'LIVE90_V4_HEALTH '.json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
  exit(0);
 }
 usleep(1000000);
} while(microtime(true)<$deadline);
if(is_array($d))echo 'LIVE90_V4_DIAGNOSTIC '.json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
fwrite(STDERR,'LIVE90_V4_HEALTH_ERROR: aucun cycle décision V5 récent ou mode radar non confirmé'.PHP_EOL);exit(1);


