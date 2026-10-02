<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);foreach(['Journal','Metrics','Results','Offers','LabDraft','helpers'] as $name)require_once $root.'/public_html/includes/public-site/'.$name.'.php';require_once $root.'/public_html/includes/packs-config.php';
use StratEdgePublic\Journal;use StratEdgePublic\Results;use StratEdgePublic\Metrics;use StratEdgePublic\Offers;
function ok(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
function rejects(callable $run,string $message):void{try{$run();}catch(InvalidArgumentException|RuntimeException $e){return;}throw new Exception($message);}
$GLOBALS['frontSource']='x';ok(front_link('/offres.php#tennis')==='/offres.php?utm_source=x#tennis','Source placed after offer fragment');ok(front_link('/offres.php?format=all#multi')==='/offres.php?format=all&amp;utm_source=x#multi','Existing query or fragment lost');$GLOBALS['frontSource']='direct';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$j=new Journal($db);$j->install();$j->install();
$base=['title'=>'Guide de recette <script>alert(1)</script>','slug'=>'guide-recette','summary'=>'Un résumé de recette suffisamment long.','body'=>"## Un titre\n\nUn paragraphe de test <script>alert(1)</script> conservé en texte simple, jamais exécuté.",'kind'=>'guide','sport'=>'football','decision'=>'observation','sources'=>'https://example.test/source'];
$id=$j->save($base);ok(!$j->get($id),'A draft was exposed');ok(count($j->recent())===0,'Draft in listing');ok(count($j->revisions($id))===0,'Draft in revision trail');
$future=gmdate('Y-m-d\TH:i:s\Z',time()+86400);$j->save($base,$id,1,'published',$future);ok(!$j->get($id),'Scheduled article exposed');ok(count($j->revisions($id))===0,'Scheduled revision exposed');
$j->save($base,$id,2,'published');$post=$j->get($id);ok($post!==null&&$post['version']==3,'Publishing failed');
rejects(fn()=>$j->save($base,$id,2,'published',$post['publish_at'],'Correction concurrente'),'Stale write accepted');
rejects(fn()=>$j->save($base,$id,3,'draft',$post['publish_at'],'Retrait de l’article'),'Public post unpublished');
rejects(fn()=>$j->save(array_replace($base,['slug'=>'autre']),$id,3,'published',$post['publish_at'],'Modification adresse'),'Public slug changed');
$j->save(array_replace($base,['body'=>$base['body']."\n\nCorrection visible."]),$id,3,'published',$post['publish_at'],'Précision apportée au texte');
$revisions=$j->revisions($id);ok(count($revisions)===2&&$revisions[0]['reason']==='Précision apportée au texte','Corrections missing');ok(strpos($revisions[1]['data']['body'],'Correction visible')===false,'Old version mutated');
$selection=array_replace($base,['slug'=>'selection-recette','kind'=>'analyse','decision'=>'retenu','match_label'=>'Équipe fictive A — Équipe fictive B','market'=>'+2,5 buts','odds'=>'1,79','odds_at'=>(new DateTimeImmutable('now',new DateTimeZone('Europe/Paris')))->format('Y-m-d\TH:i'),'kickoff_at'=>(new DateTimeImmutable('+2 days',new DateTimeZone('Europe/Paris')))->format('Y-m-d\TH:i')]);
$selectionId=$j->save($selection,null,0,'published');$public=$j->get($selectionId);$selection=Journal::validate($selection);
rejects(fn()=>$j->save(array_replace($selection,['odds'=>'2.05']),$selectionId,1,'published',$public['publish_at'],'Réécriture de la cote'),'Public odds rewritten');
rejects(fn()=>$j->save(array_replace($selection,['slug'=>'trop-tard','kickoff_at'=>'2020-01-01T21:00']),null,0,'published'),'Postmatch initial selection accepted');
rejects(fn()=>$j->save(array_replace($selection,['slug'=>'trop-tot','result'=>'gagne']),null,0,'published'),'New winning selection accepted');
rejects(fn()=>$j->save(array_replace($selection,['slug'=>'sans-sources','sources'=>'']),null,0,'published'),'Analysis without sources accepted');
rejects(fn()=>Journal::validate(array_replace($base,['sources'=>'javascript:alert(1)'])),'Unsafe source');
rejects(fn()=>Journal::validate(array_replace($base,['odds'=>'1e309'])),'Infinite odds');
$j->save(array_replace($selection,['result'=>'perdu','score'=>'0–0']),$selectionId,1,'published',$public['publish_at'],'Résultat fictif de recette');ok($j->get($selectionId)['data']['result']==='perdu','Result cannot be recorded');
$stats=Results::calculate([['resultat'=>'gagne','cote'=>'1,40'],['resultat'=>'perdu','cote'=>'2'],['resultat'=>'annule','cote'=>null],['resultat'=>'pending','cote'=>'2']]);ok($stats['roi']===-30.0&&$stats['rate']===50.0&&$stats['voids']===1,'ROI maths / voids');
$stats=Results::calculate([['resultat'=>'gagne','cote'=>null],['resultat'=>'perdu','cote'=>'2']]);ok($stats['roi']===null&&$stats['missing']===1,'Missing odds advertise ROI');ok(Results::calculate([])['rate']===null,'Empty history advertises rate');
Metrics::count($db,'article','x');Metrics::count($db,'article','x');ok((int)Metrics::report($db)[0]['total']===2,'Aggregate counter');Metrics::count(new PDO('sqlite::memory:'),'article','x');ok(Metrics::source(['utm_source'=>['bad']],'https://t.co/test')==='x','Malformed query');
foreach(['https://evil.test','//evil.test',"/\\evil.test",'/a.php'."\r\n".'Location: https://evil.test','javascript:alert(1)','%2f%2fevil.test'] as $url)ok(Offers::safeRedirect($url)==='/dashboard.php','Unsafe redirect '.$url);ok(Offers::safeRedirect('/offre.php?type=vip_max')==='/offre.php?type=vip_max','Valid local checkout');ok(Offers::target('unknown')===null,'Invalid offer');
$html=front_body($base['body']);ok(strpos($html,'<script>')===false&&strpos($html,'&lt;script&gt;')!==false,'HTML injection');ok(front_date('2026-10-02T21:00',true)==='02.10.2026 · 21:00','Paris wall-clock date changed');
$draft=\StratEdgePublic\LabDraft::make(['home'=>'A','away'=>'B','league'=>'Compétition de recette','key'=>'secret-key','kickoff'=>gmdate('c',time()+86400),'pick'=>['label'=>'+2,5 buts','odds'=>1.8,'probability'=>.6],'api_key'=>'must-never-leak','footystats'=>['home'=>['n'=>8],'away'=>['n'=>9],'source'=>['competition'=>'Recette']]],['version'=>'test','generated_at'=>gmdate('c'),'api_key'=>'must-never-leak']);ok($draft['decision']==='observation'&&strpos(Journal::encode($draft),'must-never-leak')===false,'Lab draft leaks private payload / assumes selection');
foreach(require $root.'/public_html/includes/public-site/seeds.php' as $seed)$j->save($seed,null,0,'published');ok(count($j->recent())===5,'Seed guides not published');
$db->exec('CREATE TABLE bets (id INTEGER,titre TEXT,cote TEXT,resultat TEXT,date_post TEXT,date_resultat TEXT,private_analysis TEXT)');
$db->exec("INSERT INTO bets VALUES (1,'Archive de recette','2.0','gagne','2026-01-01','2026-01-02','PRIVATE-NOT-PUBLIC')");
$legacy=Results::load($db);ok(count($legacy)===1&&Results::category($legacy[0])==='multi'&&!array_key_exists('private_analysis',$legacy[0]),'Legacy schema compatibility or private data filtering');
// Regression: large images/private payloads must not be fetched into PHP's web memory budget.
$bulk=$db->prepare("INSERT INTO bets VALUES (?, 'Archive volumineuse', '1.8', 'perdu', '2026-01-01', '2026-01-02', ?)");
$privatePayload=str_repeat('x', 2*1024*1024);
for($i=2;$i<=25;$i++)$bulk->execute([$i,$privatePayload]);
unset($privatePayload);ini_set('memory_limit','32M');$bulkRows=Results::load($db);
ok(count($bulkRows)===25&&!array_key_exists('private_analysis',$bulkRows[0]),'Heavy private payload entered public history memory');
$adminRows=Results::loadAdmin($db);ok(count($adminRows)===25&&array_key_exists('image_path',$adminRows[0])&&!array_key_exists('private_analysis',$adminRows[0]),'Admin reader fetched blobs or lost image references');
$imageRows=Results::loadWithImages($db);ok(count($imageRows)===25&&array_key_exists('image_path',$imageRows[0])&&array_key_exists('sport',$imageRows[0])&&!array_key_exists('private_analysis',$imageRows[0]),'Tipster reader fetched blobs or lost filter fields');
echo "PUBLIC_CORE_OK drafts, scheduling, revisions, immutable selections, samples, safe links, escaping, ROI, metrics, seeds\n";
