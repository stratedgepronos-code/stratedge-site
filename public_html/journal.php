<?php
require __DIR__.'/includes/public-site/bootstrap.php';
front_count('journal');$filter=is_string($_GET['type']??null)?$_GET['type']:'';if(!in_array($filter,['','guide','analyse','debrief','coulisses'],true))$filter='';
$posts=front_posts(200);if($filter!=='')$posts=array_values(array_filter($posts,fn($p)=>$p['data']['kind']===$filter));
$pageActive='journal';$pageTitle='Le journal — Analyses, guides et débriefs | StratEdge';$pagePath='/journal.php';
require __DIR__.'/includes/public-site/views/journal.php';
