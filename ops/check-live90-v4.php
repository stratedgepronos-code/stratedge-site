<?php
declare(strict_types=1);
// Lecture seule : ne révèle jamais les clés et n’envoie aucun message.
$root=$argv[1]??'';
require $root.'/public_html/api/live90/core.php';
// Vérifier aussi les fichiers servis par PHP, même quand le moteur ne change pas.
foreach (['app.js','shell.html','style.css','packball.js'] as $asset) {
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
for($attempt=0;$attempt<12;$attempt++){
 $proc=proc_open(['/usr/bin/python3','/opt/stratedge/live90/live_v4.py',$db,'--diagnostic'],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['file','/dev/null','a']],$pipes,null,$env);
 if(!is_resource($proc))throw new RuntimeException('Diagnostic Python indisponible');
 $text=stream_get_contents($pipes[1]);fclose($pipes[1]);$status=proc_close($proc);$d=json_decode($text,true);
 $at=is_array($d)?strtotime((string)($d['engine']['at']??'')):false;
 if($status===0&&$at!==false&&abs(time()-$at)<=65&&($d['engine']['version']??'')==='live4.0'){
  echo 'LIVE90_V4_HEALTH '.json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
  exit(0);
 }
 usleep(1000000);
}
fwrite(STDERR,'LIVE90_V4_HEALTH_ERROR: aucun cycle moteur V4 récent'.PHP_EOL);exit(1);
