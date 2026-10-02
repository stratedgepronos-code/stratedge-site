<?php
require __DIR__.'/bootstrap.php';
front_count('resultats');$bets=front_results();$sport=is_string($_GET['sport']??null)?$_GET['sport']:'all';if(!in_array($sport,['all','multi','tennis','fun'],true))$sport='all';
$period=is_string($_GET['periode']??null)?$_GET['periode']:'all';if(!in_array($period,['all','30','90'],true))$period='all';
$since=$period==='all'?null:(new DateTimeImmutable('now',new DateTimeZone('Europe/Paris')))->modify('-'.(int)$period.' days')->format('Y-m-d H:i:s');
$bets=array_values(array_filter($bets,fn($b)=>($sport==='all'||\StratEdgePublic\Results::category($b)===$sport)&&($since===null||($b['date_resultat']??$b['date_post'])>=$since)));
$stats=\StratEdgePublic\Results::calculate($bets);$pages=max(1,(int)ceil(count($bets)/40));$page=max(1,min($pages,(int)($_GET['page']??1)));$visibleBets=array_slice($bets,($page-1)*40,40);
$pageActive='resultats';$pagePath='/historique.php?vue=bilan';$pageTitle='Résultats et historique des paris — Bilan public | StratEdge';$pageDescription='Consultez les paris gagnés, perdus et annulés de StratEdge. Taux de réussite, périodes, cotes et méthode du ROI à mise fixe.';
require __DIR__.'/views/results.php';
