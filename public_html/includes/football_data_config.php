<?php
// ============================================================
// STRATEDGE — Configuration des API Football (import matchs)
//
// 1) API-Football — RECOMMANDÉE (la première configurée sera utilisée)
//    • api_football_key = clé obtenue sur https://dashboard.api-football.com/register (accès direct)
//    • api_football_rapidapi_key = clé obtenue sur https://rapidapi.com/api-sports/api/api-football (RapidAPI)
//    ⚠️ Ce sont 2 clés différentes : ne pas mélanger. Une seule suffit.
//
// 2) Football-Data.org — fallback
//    • api_key = clé sur https://www.football-data.org/client/register (~12 compétitions)
//
// 3) Filtre ligues (optionnel) — par ID de ligue API-Football (fiable).
//    Liste des IDs : https://dashboard.api-football.com/soccer/ids (onglet Leagues).
//    Si allowed_league_ids n'est pas vide, seuls ces championnats sont importés.
//    Laisser vide = importer toutes les ligues.
// ============================================================
return [
    'api_football_key'         => '', // Clé directe api-sports.io (dashboard.api-football.com)
    'api_football_rapidapi_key'=> '', // Clé RapidAPI (si tu as souscrit via RapidAPI)
    'api_key'                  => '', // Clé Football-Data.org

    // IDs des ligues à importer (voir dashboard.api-football.com/soccer/ids). Vide = toutes.
    'allowed_league_ids' => [
        2,    // UEFA Champions League
        3,    // UEFA Europa League
        848,  // UEFA Europa Conference League
        39,   // Premier League (England)
        40,   // Championship (England)
        135,  // La Liga (Spain)
        136,  // La Liga 2 (Spain)
        140,  // Copa Del Rey (Spain)
        71,   // Serie A (Italy)
        72,   // Serie B (Italy)
        78,   // Bundesliga (Germany)
        79,   // 2. Bundesliga (Germany)
        81,   // DFB Pokal (Germany)
        61,   // Ligue 1 (France)
        62,   // Ligue 2 (France)
        88,   // Eredivisie (Netherlands)
        94,   // Liga Portugal
        203,  // Super Lig (Turkey)
        207,  // Serie A (Brazil)
        253,  // MLS (USA)
        262,  // Liga MX (Mexico)
        283,  // Liga Profesional (Argentina)
        271,  // Primera Division (Uruguay)
        235,  // Premier League (Scotland)
        169,  // Super League (Switzerland)
        113,  // Allsvenskan (Sweden)
        103,  // Eliteserien (Norway)
        239,  // Pro League (Belgium)
        299,  // J1 League (Japan)
        268,  // Copa Libertadores
        269,  // Copa Sudamericana
        5,    // UEFA Nations League
        1,    // World Cup
        4,    // European Championship
    ],
];
