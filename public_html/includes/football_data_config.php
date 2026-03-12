<?php
// ============================================================
// STRATEDGE — Configuration des API Football (import matchs)
//
// Deux API supportées (la première configurée sera utilisée) :
//
// 1) API-Football (api-sports.io) — RECOMMANDÉE
//    → Couvre TOUTES les compétitions (800+ ligues, 100 req/jour)
//    → Inscription gratuite : https://dashboard.api-football.com/register
//
// 2) Football-Data.org
//    → Couvre ~12 compétitions majeures seulement (PL, Liga, Ligue 1…)
//    → Inscription gratuite : https://www.football-data.org/client/register
// ============================================================
return [
    'api_football_key' => '', // Clé API-Football (api-sports.io) — prioritaire
    'api_key'          => '', // Clé Football-Data.org — fallback
];
