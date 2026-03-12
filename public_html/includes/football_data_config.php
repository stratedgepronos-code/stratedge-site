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
// ============================================================
return [
    'api_football_key'         => '', // Clé directe api-sports.io (dashboard.api-football.com)
    'api_football_rapidapi_key'=> '', // Clé RapidAPI (si tu as souscrit via RapidAPI)
    'api_key'                  => '', // Clé Football-Data.org
];
