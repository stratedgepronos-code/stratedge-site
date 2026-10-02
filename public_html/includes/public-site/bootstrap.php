<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/auth.php';
require_once __DIR__.'/Journal.php';
require_once __DIR__.'/Metrics.php';
require_once __DIR__.'/Results.php';
require_once __DIR__.'/helpers.php';
require_once dirname(__DIR__).'/packs-config.php';
$frontSource=\StratEdgePublic\Metrics::source($_GET,$_SERVER['HTTP_REFERER']??'');
$frontMember=isLoggedIn();
$frontDb=null;$journal=null;$posts=[];$bets=[];$frontUnavailable=false;
try { $frontDb=getDB();$journal=new \StratEdgePublic\Journal($frontDb); } catch(Throwable $e) { $frontUnavailable=true; error_log('[public-site] database unavailable'); }
function front_count(string $event): void { if($GLOBALS['frontDb']) \StratEdgePublic\Metrics::count($GLOBALS['frontDb'],$event,$GLOBALS['frontSource']); }
function front_posts(int $limit=60): array {
    try { return $GLOBALS['journal'] ? $GLOBALS['journal']->recent(false,$limit) : []; } catch(Throwable $e) { $GLOBALS['frontUnavailable']=true;error_log('[public-site] journal unavailable');return []; }
}
function front_results(): array {
    try { return $GLOBALS['frontDb'] ? \StratEdgePublic\Results::load($GLOBALS['frontDb']) : []; } catch(Throwable $e) { $GLOBALS['frontUnavailable']=true;error_log('[public-site] results unavailable');return []; }
}
