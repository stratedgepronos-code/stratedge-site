<?php
// STRATEDGE ODDS ENGINE — config (copier en config.local.php, jamais versionne)
return [
    'provider' => 'theoddsapi',           // the-odds-api.com (v4) — OddsPapi: futur driver
    'api_key'  => 'VOTRE_CLE_API',
    'regions'  => 'eu',
    'markets'  => 'h2h,totals',
    'sports'   => [
        'soccer_france_ligue_one',
        'soccer_epl',
        'soccer_uefa_champs_league',
        'tennis_atp', 'tennis_wta',
    ],
    'ref_book' => 'pinnacle',
    'fr_books' => ['betclic', 'unibet_eu', 'winamax_fr'],
    'closing_window_min' => 10,
];
