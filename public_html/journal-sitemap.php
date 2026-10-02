<?php
require __DIR__.'/includes/public-site/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');
try { if(!$frontDb)throw new RuntimeException();$q=$frontDb->prepare("SELECT slug,updated_at FROM se_public_posts WHERE status='published' AND publish_at<=? ORDER BY publish_at DESC LIMIT 45000");$q->execute([\StratEdgePublic\Journal::now()]);$rows=$q->fetchAll(PDO::FETCH_ASSOC); }
catch(Throwable $e){http_response_code(503);echo '<?xml version="1.0" encoding="UTF-8"?><error>Journal temporairement indisponible</error>';exit;}
echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><?php foreach($rows as $row): ?><url><loc>https://stratedgepronos.fr/article.php?slug=<?= front_h($row['slug']) ?></loc><lastmod><?= front_h($row['updated_at']) ?></lastmod></url><?php endforeach; ?></urlset>
