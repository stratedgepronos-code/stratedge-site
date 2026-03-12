<?php
// ============================================================
// STRATEDGE — Configuration des API Football (import matchs)
//
// Deux API supportées (la première configurée sera utilisée) :
//
// 1) API-Football — RECOMMANDÉE
//    → Couvre TOUTES les compétitions (800+ ligues, 100 req/jour)
//    → Fonctionne avec clé directe (api-sports.io) OU clé RapidAPI
//    → Inscription : https://dashboard.api-football.com/register
//      ou via RapidAPI : https://rapidapi.com/api-sports/api/api-football
//
// 2) Football-Data.org
//    → Couvre ~12 compétitions majeures seulement (PL, Liga, Ligue 1…)
//    → Inscription gratuite : https://www.football-data.org/client/register
// ============================================================
return [
    'api_football_key' => '', // Clé API-Football (directe OU RapidAPI) — prioritaire
    'api_key'          => '', // Clé Football-Data.org — fallback
];
