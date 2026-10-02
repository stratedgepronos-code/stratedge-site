<?php
require __DIR__.'/includes/public-site/bootstrap.php';
$post=null;$revisions=[];$slug=is_string($_GET['slug']??null)?$_GET['slug']:'';
try { if($journal && preg_match('/^[a-z0-9-]{1,180}$/D',$slug))$post=$journal->get($slug);if($post)$revisions=$journal->revisions($post['id']); } catch(Throwable $e) { $frontUnavailable=true;error_log('[public-site] article unavailable'); }
if(!$post){http_response_code($frontUnavailable?503:404);$pageTitle=$frontUnavailable?'Journal indisponible | StratEdge':'Article introuvable | StratEdge';$frontNoIndex=true;require __DIR__.'/includes/public-site/header.php';echo '<section class="section wrap"><h1 class="section-title">'.($frontUnavailable?'Le journal revient bientôt.':'Cette page n’est pas publiée.').'</h1><p style="margin-top:25px"><a class="button" href="/journal.php">Revenir au journal →</a></p></section>';require __DIR__.'/includes/public-site/footer.php';exit;}
front_count('article');$pageActive='article';$pageTitle=$post['data']['title'].' | StratEdge';$pageDescription=$post['data']['summary'];$pagePath=front_article_url($post);
require __DIR__.'/includes/public-site/views/article.php';
