<?php
// CLI-only fixtures: render real templates with synthetic data, no auth/DB/network.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__,2);$variant=$argv[1]??'index';$ordinary=($argv[2]??'')==='ordinary';
function isSuperAdmin(){global $ordinary;return !$ordinary;}
function getAdminRole(){return 'admin_foot';}
function clean($v){return htmlspecialchars((string)$v,ENT_QUOTES);}
function csrfToken(){return 'fixture-only';}
function getMemberRank($db,$id){return ['class'=>'rank-member','label'=>'Membre'];}
class FixtureQuery {function fetchColumn(){return 3;}function execute($v=[]){}function fetchAll(){return [];} }
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
}elseif(in_array($variant,['membres','creer-card'])){
 $error=$success='';$membreDetail=null;$membres=$derniersMembres;$search='';$adminRole='super_admin';$isAdminFunSport=$isAdminTennis=false;$seAdminFetchPrefix='/panel-x9k3m';
 $file=$root.'/public_html/admin/'.$variant.'.php';$source=file_get_contents($file);$source=substr($source,strpos($source,'<!DOCTYPE html>'));$source=str_replace('__DIR__',var_export(dirname($file),true),$source);eval('?>'.$source);
}elseif($variant==='live90'){
 $assets=$root.'/public_html/admin/slate/live90/assets/';
 echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Live · recette locale</title><style>';readfile($assets.'style.css');echo '</style></head><body>';
 require $root.'/public_html/admin/sidebar.php';echo '<main class="main">';readfile($assets.'shell.html');echo '</main></body></html>';
}
