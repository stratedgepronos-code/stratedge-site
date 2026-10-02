<?php
require_once __DIR__.'/includes/public-site/bootstrap.php';
require_once __DIR__.'/includes/public-site/Offers.php';
front_count('offres');$offers=\StratEdgePublic\Offers::all();$pageActive='offres';$pagePath='/offres.php';$pageTitle='Les offres et tarifs — Multisports, Tennis, Fun, VIP | StratEdge';$pageDescription='Découvrez les offres StratEdge avant inscription : crédits Multisports, Tennis 15 € pour 7 jours, Fun 10 € pour 7 jours, VIP Max 50 € pour 30 jours.';
require __DIR__.'/includes/public-site/views/offers.php';
