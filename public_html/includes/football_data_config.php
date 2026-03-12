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

    // IDs des ligues (tes 2 screens). Référence : https://dashboard.api-football.com/soccer/ids
    // 1=FIFA CWC 2=Champions 3=Europa 4=Euro 5=Nations League 39=Premier 40=Championship
    // 61=Ligue1 62=Ligue2 71=Serie A 72=Serie B 78=Bundesliga 79=2.Bundesliga 81=DFB Pokal
    // 88=Eredivisie 94=Liga Portugal 135=LaLiga 136=LaLiga2 137=Coppa 143=Copa Rey
    // 169=Super League CH 197=Scotland 203=Super Lig 207=Serie A BR 218=Austria 235=A-League/Scotland
    // 239=Pro League BE 242=Ecuador 253=MLS 262=Liga MX 268=Copa Lib 269=Copa Sud 271=Uruguay
    // 278=Paraguay 283=Argentina 292=K League 307=Saudi 384=CONCACAF 565=Leagues Cup 635=Women EURO 848=Conf League
    'allowed_league_ids' => [
        1, 2, 3, 4, 5, 39, 40, 48, 61, 62, 71, 72, 78, 79, 81, 88, 94, 98, 103, 106, 113, 119,
        135, 136, 137, 143, 169, 197, 203, 207, 218, 235, 239, 242, 253, 262, 268, 269, 271,
        278, 283, 292, 307, 384, 565, 635, 848,
    ],
];
