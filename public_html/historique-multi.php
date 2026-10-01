<?php
// Page détail tipster MULTI — réutilise toute la logique de l'ancienne historique
// mais filtrée sur categorie='multi' uniquement
$_GET['_tipster_filter'] = 'multi';
require __DIR__ . '/_historique-tipster.php';
