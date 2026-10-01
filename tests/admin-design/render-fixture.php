<?php
// CLI-only fixtures: render real templates with synthetic data, no auth/DB/network.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__,2);$variant=$argv[1]??'index';$ordinary=($argv[2]??'')==='ordinary';
parse_str($argv[3]??'',$_GET);$_POST=[];
function isSuperAdmin(){global $ordinary;return !$ordinary;}
function getAdminRole(){return 'admin_foot';}
function clean($v){return htmlspecialchars((string)$v,ENT_QUOTES);}
function csrfToken(){return 'fixture-only';}
function getMemberRank($db,$id){return ['class'=>'rank-member','label'=>'Membre'];}
function betImageUrl($v){return '/fixture-bet.svg';}
class FixtureQuery {function fetchColumn(){return 3;}function execute($v=[]){}function fetchAll(){return [];}function fetch($mode=null){global $steps;return $steps[0]??false;} }
class FixtureDB {function query($v){return new FixtureQuery;}function prepare($v){return new FixtureQuery;}}
$db=new FixtureDB;$pageActive=$variant;
$nbMembres=1248;$nbAboActifs=86;$nbBets=12;$nbTickets=3;$nbMessages=7;
$revenuMulti=6294.5;$revenuTennis=3180;$revenuFun=1420;$revenuVip=3960;$revenuTotal=14854.5;
$nbAchatsMulti=486;$nbAboTennis=212;$nbAboFun=142;$nbAboVip=88;$nbVipActifs=24;$fondateurPlaces=8;$fondateurRestant=2;
$visiteursAujourdhui=327;$visiteursSemaine=2184;$visiteursMois=9428;$visiteursAll=68312;
$derniersMembres=[];for($i=1;$i<=4;$i++)$derniersMembres[]=['id'=>$i,'nom'=>'Membre exemple '.$i,'email'=>'membre'.$i.'@example.test','date_inscription'=>'2026-10-01','banni'=>0];
$derniersTickets=[['nom'=>'Membre exemple 1','sujet'=>'Question sur un abonnement','statut'=>'ouvert'],['nom'=>'Membre exemple 2','sujet'=>'Accès à mon espace','statut'=>'en_cours']];
if($variant==='empty'){$pageActive='index';$nbMembres=$nbAboActifs=$nbBets=$nbTickets=$nbMessages=$revenuMulti=$revenuTennis=$revenuFun=$revenuVip=$revenuTotal=$nbAchatsMulti=$nbAboTennis=$nbAboFun=$nbAboVip=$nbVipActifs=$fondateurPlaces=$visiteursAujourdhui=$visiteursSemaine=$visiteursMois=$visiteursAll=0;$derniersMembres=$derniersTickets=[];}
if(in_array($variant,['index','empty'])){
 echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Recette locale · données fictives</title></head><body>';
 require $root.'/public_html/admin/sidebar.php';echo '<main class="main">';require $root.'/public_html/admin/design/dashboard.php';echo '</main></body></html>';
}elseif(in_array($variant,['membres','creer-card','prono-commu-admin','montante-tennis','montante-foot','ht-tracker','edit-bet-image','historique','broadcast','twitter-post'])){
 $error=$success='';$membreDetail=null;$membres=$derniersMembres;$search='';$adminRole='super_admin';$isAdminFunSport=$isAdminTennis=false;$seAdminFetchPrefix='/panel-x9k3m';
 $pauseActive='0';$pauseDate='';$apiFootballRapidKey=$footballDataKey='';$hasAnyKey=false;$tomorrow=$tomorrowParis='2026-10-03';
 $allMatches=[['id'=>1,'match_date'=>'2026-10-03','team_home'=>'Équipe exemple A','team_away'=>'Équipe exemple B','competition'=>'Championnat de démonstration','vote_closed_at'=>'2026-10-02 20:00:00','nb_votes'=>26,'is_winner'=>1,'resultat'=>'en_cours']];$winners=$allMatches;
 $config=['id'=>1,'nom'=>'Montante de démonstration','statut'=>'active','bankroll_initial'=>100,'mise_depart'=>10,'date_debut'=>'2026-10-01','created_at'=>'2026-10-01','webhook_url'=>'','webhook_url_image'=>''];
 $steps=[['id'=>1,'step_number'=>1,'match_desc'=>'Équipe exemple A — Équipe exemple B','competition'=>'Championnat de démonstration','date_match'=>'2026-10-03','heure'=>'21:00','cote'=>1.8,'mise'=>10,'resultat'=>'en_cours','gain_perte'=>null,'bankroll_apres'=>null,'pronostic'=>'Plus de 2,5 buts','analyse'=>'Analyse fictive pour vérifier la présentation.']];$toutesMontantes=[$config];
 $bets=[];foreach(['gagne','perdu','annule'] as $i=>$resultat)$bets[]=['id'=>$i+1,'titre'=>'Rencontre de démonstration '.($i+1),'image_path'=>'fixture.svg','locked_image_path'=>'','resultat'=>$resultat,'type'=>'safe','date_post'=>'2026-10-01','date_resultat'=>'2026-10-01','categorie'=>'multi'];
 $typeLabels=['safe'=>'Safe','fun'=>'Fun','live'=>'Live'];$resultatLabels=['gagne'=>'Gagné','perdu'=>'Perdu','annule'=>'Annulé'];
 $onglet=$_GET['onglet']??'multi';$moisOuvert=$_GET['mois']??'2026-10';$moisNoms=['10'=>'Octobre'];$moisMulti=$moisHockey=['2026-10'=>$bets];$moisTennis=['2026-10'=>array_map(fn($b)=>array_merge($b,['categorie'=>'tennis']),$bets)];
 $statsMulti=$statsTennis=$statsHockey=['total'=>3,'gagnes'=>1,'perdus'=>1,'annules'=>1,'taux'=>50];
 $resultatConfig=[];foreach(['gagne'=>'#00c864','perdu'=>'#ff4444','annule'=>'#f59e0b'] as $key=>$color)$resultatConfig[$key]=['label'=>$resultatLabels[$key],'color'=>$color,'bg'=>$color.'18','border'=>$color.'50'];
 $nbTotal=1248;$nbAbonnes=86;$packStats=['daily'=>42,'weekly'=>18,'weekend'=>12,'rasstoss'=>14];
 $apiOk=false;$activeTab=$_GET['tab']??'compose';$logs=$templates=[];
 $file=$root.'/public_html/admin/'.$variant.'.php';$source=file_get_contents($file);$source=substr($source,strpos($source,'<!DOCTYPE html>'));$source=str_replace('__DIR__',var_export(dirname($file),true),$source);eval('?>'.$source);
}elseif($variant==='live90'){
 $assets=$root.'/public_html/admin/slate/live90/assets/';
 echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Live · recette locale</title><style>';readfile($assets.'style.css');echo '</style></head><body>';
 require $root.'/public_html/admin/sidebar.php';echo '<main class="main">';readfile($assets.'shell.html');echo '</main></body></html>';
}
