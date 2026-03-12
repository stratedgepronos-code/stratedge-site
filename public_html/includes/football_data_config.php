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
// 3) Filtre ligues (optionnel) — si la liste n'est pas vide, seuls ces championnats sont importés.
//    Noms exacts tels que retournés par l'API (ex: "Premier League", "Ligue 1", "Champions League").
//    Laisser vide = importer toutes les ligues.
// ============================================================
return [
    'api_football_key'         => '', // Clé directe api-sports.io (dashboard.api-football.com)
    'api_football_rapidapi_key'=> '', // Clé RapidAPI (si tu as souscrit via RapidAPI)
    'api_key'                  => '', // Clé Football-Data.org

    // Ligues à importer pour le prono commu (noms exacts API, d'après tes screens). Vide = toutes.
    'allowed_leagues' => [
        // Screen 1 — International / Europe / Monde
        'FIFA Club World Cup',
        'European Championship',
        'Euro Qualification',
        'WC Qualification Europe',
        'WC Qualification South America',
        'WC Qualification Concacaf',
        'CAF World Cup Qualifiers',
        'WC Qualification Asia',
        'UEFA Nations League',
        'Champions League',
        'Premier League',
        'La Liga',
        'Serie A',
        'Bundesliga',
        'Copa Libertadores',
        'Europa League',
        'Ligue 1',
        'Eredivisie',
        'Liga Portugal',
        'Copa Sudamericana',
        'Europa Conference League',
        'UEFA Women\'s EURO',
        'Pro League',
        'Liga Profesional de Fútbol',
        'A-League Men',
        'Admiral Bundesliga',
        'Superliga',
        'Liga Pro',
        // Screen 2 — D2, coupes, autres pays
        'Championship',
        'La Liga 2',
        'Copa Del Rey',
        'Ligue 2',
        '2. Bundesliga',
        'DFB Pokal',
        'Serie B',
        'Coppa Italia',
        'J1 League',
        'K League 1',
        'Liga MX',
        'Eliteserien',
        'Division 1',
        'Ekstraklasa',
        'Premiership',
        'Super League',
        'Allsvenskan',
        'Super Lig',
        'Primera Division',
        'Major League Soccer',
        'CONCACAF Champions Cup',
        'Premier Division',
        'Leagues Cup',
        'Liga BetPlay',
        'Veikkausliiga',
    ],
];
