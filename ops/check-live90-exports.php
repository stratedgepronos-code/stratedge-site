<?php
declare(strict_types=1);
// Generate the real admin exports locally; log only schema, size and duration.
// No Telegram calls, no credentials in output and no export files left on disk.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = $argv[1] ?? '';
require $root.'/public_html/api/live90/core.php';
$config = config90();
$db = $config['SE90_DB'] ?? '/var/lib/stratedge/live90.sqlite';
$env = ['PATH'=>'/usr/bin:/bin','LANG'=>'C.UTF-8','PYTHONIOENCODING'=>'utf-8','PYTHONDONTWRITEBYTECODE'=>'1'];
foreach (['export_review'=>'stratedge.audit.v4','export_learning'=>'stratedge.learning.v1'] as $action=>$schema) {
    $start = microtime(true);
    $command = ['/usr/bin/timeout','--signal=TERM','--kill-after=2','35','/usr/bin/python3',$root.'/live90/server/live_v4.py',$db];
    $proc = proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['file','/dev/null','a']],$pipes,null,$env);
    if (!is_resource($proc)) throw new RuntimeException('LIVE90_EXPORT_START_FAILED: '.$action);
    fwrite($pipes[0],json_encode(['action'=>$action],JSON_THROW_ON_ERROR));
    fclose($pipes[0]);
    $body = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $code = proc_close($proc);
    $payload = json_decode($body,true);
    if ($code !== 0 || !is_array($payload) || ($payload['schema'] ?? '') !== $schema) {
        throw new RuntimeException('LIVE90_EXPORT_FAILED: '.$action.' exit='.$code);
    }
    echo 'LIVE90_EXPORT_OK '.json_encode(['action'=>$action,'schema'=>$schema,'bytes'=>strlen($body),'seconds'=>round(microtime(true)-$start,3)],JSON_UNESCAPED_SLASHES).PHP_EOL;
}
