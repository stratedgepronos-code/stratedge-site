<?php
// Copier en config.local.php SUR LE SERVEUR uniquement (fichier ignoré par Git).
// Choisir un modèle Responses compatible web_search et autorisé sur votre compte.
return [
    'api_key' => '',
    'model' => '',
    // Facultatif : FOOTYSTATS_API_KEY du serveur est réutilisée par défaut.
    'footystats_api_key' => '',
    // Alias explicites uniquement, nom PackBall => nom FootyStats.
    'footystats_team_aliases' => [],
];
