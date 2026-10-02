<?php
require __DIR__.'/includes/public-site/bootstrap.php';
front_count('accueil');$posts=front_posts(3);$resultStats=\StratEdgePublic\Results::calculate(front_results());
require __DIR__.'/includes/public-site/views/home.php';
