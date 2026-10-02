<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=$argv[1]??dirname(__DIR__);
require_once $root.'/public_html/includes/db.php';require_once $root.'/public_html/includes/public-site/Journal.php';
try{$db=getDB();$journal=new \StratEdgePublic\Journal($db);$journal->install();$created=0;
foreach(require $root.'/public_html/includes/public-site/seeds.php' as $article){if($journal->get($article['slug'],true))continue;$article+=['author'=>'La rédaction StratEdge','decision'=>'observation','result'=>'en_attente','sources'=>''];$journal->save($article,null,0,'published');$created++;}
echo 'PUBLIC_JOURNAL_READY guides_created='.$created."\n";
}catch(Throwable $e){fwrite(STDERR,"PUBLIC_JOURNAL_ERROR: installation failed; consult application database configuration\n");exit(1);}
