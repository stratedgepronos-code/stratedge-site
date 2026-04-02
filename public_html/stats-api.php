<?php
/**
 * STRATEDGE FOOTYSTATS API PROXY
 * ================================
 * Proxy PHP pour FootyStats API.
 * - Cache la clé API côté serveur
 * - Retourne stats pré-calculées (Over%, BTTS%, xG, corners, H2H...)
 * - Accessible par Claude via web_fetch
 * 
 * URL : https://stratedgepronos.fr/stats-api.php
 *
 * Cache BDD (même clé FOOTYSTATS) : plugin plugins/football_footystats/
 *   + cron/sync-footystats-cache.php — tables fd_fy_*
 * 
 * USAGE :
 *   ?action=today                                → matchs du jour avec stats
 *   ?action=league&id=LEAGUE_ID                  → stats de la ligue
 *   ?action=team&id=TEAM_ID                      → stats d'une équipe
 *   ?action=match&id=MATCH_ID                    → stats pré-match détaillées
 *   ?action=h2h&home=TEAM_ID&away=TEAM_ID        → head-to-head
 *   ?action=leagues                              → liste des ligues dispo
 *   ?action=search&q=arsenal                     → chercher une équipe
 * 
 * AUTH : token défini dans config-keys.php (variable AUTH_TOKEN)
 */

// ============================================
// CONFIG
// ============================================
$configFile = __DIR__ . '/config-keys.php';
if (file_exists($configFile)) { require_once $configFile; }

if (!defined('FOOTYSTATS_API_KEY') || !defined('AUTH_TOKEN')) {
    http_response_code(503);
    echo json_encode(["error" => "Configuration serveur manquante. Contactez l'administrateur."]);
    exit;
}
$FOOTYSTATS_KEY = FOOTYSTATS_API_KEY;
$AUTH_TOKEN     = AUTH_TOKEN;
$BASE_URL = "https://api.footystats.org";

// ============================================
// HEADERS
// ============================================
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: https://stratedgepronos.fr");
header("Cache-Control: public, max-age=600"); // cache 10 min

// ============================================
// AUTH CHECK
// ============================================
$token = $_GET['token'] ?? $_SERVER['HTTP_X_STRATEDGE_TOKEN'] ?? '';
if (!hash_equals($AUTH_TOKEN, $token)) {
    http_response_code(403);
    echo json_encode(["error" => "Accès non autorisé."]);
    exit;
}

// ============================================
// ROUTES
// ============================================
$action = $_GET['action'] ?? 'today';

switch ($action) {

    case 'today':
        // Matchs du jour avec stats
        $date = $_GET['date'] ?? date('Y-m-d');
        $url = "$BASE_URL/todays-matches?key=$FOOTYSTATS_KEY&date=$date";
        $data = fetchApi($url);
        
        if (isset($data['data']) && is_array($data['data'])) {
            $matches = [];
            foreach (array_slice($data['data'], 0, 30) as $m) {
                $matches[] = extractMatchStats($m);
            }
            outputJson([
                "date" => $date,
                "matches_count" => count($matches),
                "matches" => $matches
            ]);
        } else {
            outputJson($data);
        }
        break;

    case 'match':
        // Stats détaillées d'un match
        $matchId = $_GET['id'] ?? '';
        if (!$matchId) {
            outputJson(["error" => "ID match requis. Ex: ?action=match&id=123456"]);
            break;
        }
        $url = "$BASE_URL/matches?key=$FOOTYSTATS_KEY&matchId=$matchId";
        $data = fetchApi($url);
        outputJson($data);
        break;

    case 'league':
        // Stats d'une ligue
        $leagueId = $_GET['id'] ?? '';
        $season = $_GET['season'] ?? '';
        if (!$leagueId) {
            outputJson(["error" => "ID ligue requis. Ex: ?action=league&id=1625"]);
            break;
        }
        $url = "$BASE_URL/league-matches?key=$FOOTYSTATS_KEY&season_id=$leagueId";
        if ($season) $url .= "&season=$season";
        $data = fetchApi($url);
        
        if (isset($data['data']) && is_array($data['data'])) {
            // Filtrer les matchs upcoming
            $upcoming = [];
            $now = time();
            foreach ($data['data'] as $m) {
                if (isset($m['date_unix']) && $m['date_unix'] > $now && $m['status'] !== 'complete') {
                    $upcoming[] = extractMatchStats($m);
                }
            }
            // Trier par date
            usort($upcoming, function($a, $b) {
                return ($a['date_unix'] ?? 0) - ($b['date_unix'] ?? 0);
            });
            outputJson([
                "league_id" => $leagueId,
                "upcoming_count" => count($upcoming),
                "matches" => array_slice($upcoming, 0, 20)
            ]);
        } else {
            outputJson($data);
        }
        break;

    case 'team':
        // Stats d'une équipe
        $teamId = $_GET['id'] ?? '';
        if (!$teamId) {
            outputJson(["error" => "ID équipe requis. Ex: ?action=team&id=97"]);
            break;
        }
        $url = "$BASE_URL/team?key=$FOOTYSTATS_KEY&team_id=$teamId";
        $data = fetchApi($url);
        outputJson($data);
        break;

    case 'h2h':
        // Head-to-head
        $homeId = $_GET['home'] ?? '';
        $awayId = $_GET['away'] ?? '';
        if (!$homeId || !$awayId) {
            outputJson(["error" => "IDs home et away requis. Ex: ?action=h2h&home=97&away=108"]);
            break;
        }
        $url = "$BASE_URL/head-to-head?key=$FOOTYSTATS_KEY&team1_id=$homeId&team2_id=$awayId";
        $data = fetchApi($url);
        outputJson($data);
        break;

    case 'leagues':
        // Liste des ligues
        $country = $_GET['country'] ?? '';
        $url = "$BASE_URL/league-list?key=$FOOTYSTATS_KEY&chosen_leagues_only=true";
        if ($country) $url .= "&country=$country";
        $data = fetchApi($url);
        
        if (isset($data['data']) && is_array($data['data'])) {
            $leagues = [];
            foreach ($data['data'] as $l) {
                $leagues[] = [
                    "id" => $l['id'] ?? '',
                    "name" => $l['name'] ?? '',
                    "country" => $l['country'] ?? '',
                    "season" => $l['season'] ?? ''
                ];
            }
            outputJson(["leagues_count" => count($leagues), "leagues" => $leagues]);
        } else {
            outputJson($data);
        }
        break;

    case 'search':
        // Recherche équipe
        $query = $_GET['q'] ?? '';
        if (!$query) {
            outputJson(["error" => "Query requis. Ex: ?action=search&q=arsenal"]);
            break;
        }
        $url = "$BASE_URL/team-search?key=$FOOTYSTATS_KEY&name=$query";
        $data = fetchApi($url);
        outputJson($data);
        break;

    case 'compact':
        // Stats d'un match formatées en texte compact pour Claude (~250 tokens max)
        // Usage: ?action=compact&id=MATCH_ID  OU  ?action=compact&home=Arsenal&away=Chelsea&date=2026-04-05
        $matchId  = $_GET['id']   ?? '';
        $homeName = $_GET['home'] ?? '';
        $awayName = $_GET['away'] ?? '';
        $date     = $_GET['date'] ?? date('Y-m-d');
        $matchData = null;

        if ($matchId) {
            $url = "$BASE_URL/matches?key=$FOOTYSTATS_KEY&matchId=$matchId";
            $raw = fetchApi($url);
            $matchData = isset($raw['data']) ? ($raw['data'][0] ?? null) : ($raw[0] ?? null);
        }

        if (!$matchData && ($homeName || $awayName)) {
            $url = "$BASE_URL/todays-matches?key=$FOOTYSTATS_KEY&date=$date";
            $raw = fetchApi($url);
            if (isset($raw['data']) && is_array($raw['data'])) {
                foreach ($raw['data'] as $m) {
                    $hOk = !$homeName || stripos($m['home_name'] ?? '', $homeName) !== false;
                    $aOk = !$awayName || stripos($m['away_name'] ?? '', $awayName) !== false;
                    if ($hOk && $aOk) { $matchData = $m; break; }
                }
            }
        }

        if (!$matchData) {
            outputJson(["error" => "Match introuvable", "hint" => "Utilisez ?action=today pour lister les IDs"]);
            break;
        }

        outputJson([
            "match_id"     => $matchData['id'] ?? null,
            "match"        => ($matchData['home_name'] ?? '?') . ' vs ' . ($matchData['away_name'] ?? '?'),
            "date"         => isset($matchData['date_unix']) ? date('Y-m-d H:i', $matchData['date_unix']) : null,
            "league"       => $matchData['league_name'] ?? ($matchData['competition'] ?? null),
            "home_id"      => $matchData['homeID'] ?? null,
            "away_id"      => $matchData['awayID'] ?? null,
            "compact_text" => formatCompactForClaude($matchData)
        ]);
        break;

    case 'team_stats':
        $teamId   = $_GET['id']     ?? '';
        $seasonId = $_GET['season'] ?? '';
        if (!$teamId) { outputJson(["error" => "ID équipe requis. Ex: ?action=team_stats&id=97"]); break; }
        $url = "$BASE_URL/team?key=$FOOTYSTATS_KEY&team_id=$teamId";
        if ($seasonId) $url .= "&season_id=$seasonId";
        $raw = fetchApi($url);
        $t   = $raw['data'] ?? $raw;
        outputJson([
            "team_id"      => $teamId,
            "name"         => $t['name'] ?? null,
            "compact_text" => formatTeamStatsForClaude($t)
        ]);
        break;

    default:
        outputJson([
            "error" => "Action inconnue: $action",
            "actions" => [
                "today" => "Matchs du jour avec stats (params: date=YYYY-MM-DD)",
                "match" => "Stats détaillées d'un match (params: id=MATCH_ID)",
                "league" => "Matchs à venir d'une ligue (params: id=LEAGUE_ID)",
                "team" => "Stats d'une équipe (params: id=TEAM_ID)",
                "h2h" => "Head-to-head (params: home=ID&away=ID)",
                "leagues" => "Liste des ligues choisies (params: country=England)",
                "search" => "Chercher une équipe (params: q=nom)"
            ]
        ]);
}

// ============================================
// HELPERS
// ============================================
function extractMatchStats($m) {
    return [
        "id" => $m['id'] ?? null,
        "date_unix" => $m['date_unix'] ?? null,
        "date" => isset($m['date_unix']) ? date('Y-m-d H:i', $m['date_unix']) : null,
        "competition" => $m['competition_id'] ?? null,
        "league" => $m['league_name'] ?? ($m['competition'] ?? null),
        "country" => $m['country'] ?? null,
        "home" => $m['home_name'] ?? null,
        "away" => $m['away_name'] ?? null,
        "home_id" => $m['homeID'] ?? null,
        "away_id" => $m['awayID'] ?? null,
        "status" => $m['status'] ?? null,
        "score" => ($m['homeGoalCount'] ?? '-') . '-' . ($m['awayGoalCount'] ?? '-'),
        // PRE-MATCH STATS (le GROS avantage)
        "stats" => [
            // Over/Under pré-calculé
            "home_over25_pct" => $m['home_ppg'] ?? null,
            "away_over25_pct" => $m['away_ppg'] ?? null,
            "over25_potential" => $m['o25_potential'] ?? null,
            "over15_potential" => $m['o15_potential'] ?? null,
            "over35_potential" => $m['o35_potential'] ?? null,
            // BTTS
            "btts_potential" => $m['btts_potential'] ?? null,
            // Goals
            "home_avg_goals_scored" => $m['home_avg_goals'] ?? ($m['avg_goal_home'] ?? null),
            "away_avg_goals_scored" => $m['away_avg_goals'] ?? ($m['avg_goal_away'] ?? null),
            "home_avg_goals_conceded" => $m['home_avg_goals_conceded'] ?? null,
            "away_avg_goals_conceded" => $m['away_avg_goals_conceded'] ?? null,
            // Corners
            "home_corners_avg" => $m['home_corners_avg'] ?? ($m['avg_corners_home'] ?? null),
            "away_corners_avg" => $m['away_corners_avg'] ?? ($m['avg_corners_away'] ?? null),
            "corners_potential" => $m['corners_o85_potential'] ?? null,
            // Cards
            "home_cards_avg" => $m['home_cards_avg'] ?? null,
            "away_cards_avg" => $m['away_cards_avg'] ?? null,
            // xG
            "home_xg" => $m['home_xg'] ?? ($m['team_a_xg'] ?? null),
            "away_xg" => $m['away_xg'] ?? ($m['team_b_xg'] ?? null),
            // Form
            "home_ppg" => $m['home_ppg'] ?? null,
            "away_ppg" => $m['away_ppg'] ?? null,
            // Clean sheets
            "home_cs_pct" => $m['home_cs_percentage'] ?? null,
            "away_cs_pct" => $m['away_cs_percentage'] ?? null,
            // Scoring first
            "home_scored_first_pct" => $m['home_scored_first_percentage'] ?? null,
            "away_scored_first_pct" => $m['away_scored_first_percentage'] ?? null,
        ],
        // Prediction FootyStats (si dispo)
        "prediction" => [
            "result" => $m['predicted_winner'] ?? null,
            "score" => ($m['predicted_home_goals'] ?? '?') . '-' . ($m['predicted_away_goals'] ?? '?'),
        ]
    ];
}

function fetchApi($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    $response = curl_exec($ch);
    
    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ["error" => "cURL error: $err"];
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ["error" => "FootyStats API returned HTTP $httpCode", "raw" => substr($response, 0, 300)];
    }
    
    $decoded = json_decode($response, true);
    if ($decoded === null) {
        return ["error" => "Invalid JSON", "raw" => substr($response, 0, 300)];
    }
    return $decoded;
}

// ============================================
// FORMATTERS COMPACT POUR CLAUDE (v7.2)
// Objectif : ~250 tokens par match au lieu de ~3000 tokens de JSON brut
// ============================================

/**
 * Formate les données d'un match FootyStats en texte structuré compact.
 * Extrait UNIQUEMENT les champs utiles pour le PROMPT_BETTING v7.2.
 */
function formatCompactForClaude($m) {
    $home = $m['home_name'] ?? 'Dom';
    $away = $m['away_name'] ?? 'Ext';
    $nl   = "\n";

    // --- Helper pour afficher une valeur ou "n/a" ---
    $v = function($key, $pct = false) use ($m) {
        $val = $m[$key] ?? null;
        if ($val === null || $val === '') return 'n/a';
        return $pct ? round($val, 1) . '%' : round($val, 2);
    };

    // --- Forme (derniers 5 matchs : W/D/L) ---
    $homeForm = $m['home_form'] ?? ($m['homeForm'] ?? null);
    $awayForm = $m['away_form'] ?? ($m['awayForm'] ?? null);
    $formStr  = '';
    if ($homeForm || $awayForm) {
        $formStr = $nl . "FORME_5: {$home}=" . ($homeForm ?: 'n/a') . " | {$away}=" . ($awayForm ?: 'n/a');
    }

    // --- PPG (points par match = proxy forme globale) ---
    $hPpg = $m['home_ppg'] ?? null;
    $aPpg = $m['away_ppg'] ?? null;
    $ppgStr = ($hPpg || $aPpg)
        ? $nl . "PPG: {$home}=" . ($hPpg ? round($hPpg,2) : 'n/a') . " | {$away}=" . ($aPpg ? round($aPpg,2) : 'n/a')
        : '';

    // --- xG ---
    $hXg = $m['team_a_xg'] ?? ($m['home_xg'] ?? null);
    $aXg = $m['team_b_xg'] ?? ($m['away_xg'] ?? null);
    $xgStr = ($hXg || $aXg)
        ? $nl . "xG/match: {$home}=" . ($hXg ? round($hXg,2) : 'n/a') . " | {$away}=" . ($aXg ? round($aXg,2) : 'n/a')
        : '';

    // --- Buts moyens ---
    $hGf = $m['home_avg_goals'] ?? ($m['avg_goal_home'] ?? null);
    $aGf = $m['away_avg_goals'] ?? ($m['avg_goal_away'] ?? null);
    $hGa = $m['home_avg_goals_conceded'] ?? null;
    $aGa = $m['away_avg_goals_conceded'] ?? null;
    $goalsStr = '';
    if ($hGf || $aGf) {
        $goalsStr .= $nl . "GF_moy: {$home}=" . ($hGf ? round($hGf,2) : 'n/a') . " | {$away}=" . ($aGf ? round($aGf,2) : 'n/a');
    }
    if ($hGa || $aGa) {
        $goalsStr .= $nl . "GA_moy: {$home}=" . ($hGa ? round($hGa,2) : 'n/a') . " | {$away}=" . ($aGa ? round($aGa,2) : 'n/a');
    }

    // --- Over/Under potentiels (pré-calculés par FootyStats) ---
    $o15 = isset($m['o15_potential']) ? round($m['o15_potential'],1).'%' : 'n/a';
    $o25 = isset($m['o25_potential']) ? round($m['o25_potential'],1).'%' : 'n/a';
    $o35 = isset($m['o35_potential']) ? round($m['o35_potential'],1).'%' : 'n/a';
    $ouStr = $nl . "OVER_POTENTIAL: O1.5={$o15} | O2.5={$o25} | O3.5={$o35}";

    // --- BTTS ---
    $btts = isset($m['btts_potential']) ? round($m['btts_potential'],1).'%' : 'n/a';
    $bttsStr = $nl . "BTTS_POTENTIAL: {$btts}";

    // --- Clean sheets ---
    $hCs = isset($m['home_cs_percentage']) ? round($m['home_cs_percentage'],1).'%' : 'n/a';
    $aCs = isset($m['away_cs_percentage']) ? round($m['away_cs_percentage'],1).'%' : 'n/a';
    $csStr = $nl . "CS_PCT: {$home}={$hCs} | {$away}={$aCs}";

    // --- Corners ---
    $hCrn = $m['home_corners_avg'] ?? ($m['avg_corners_home'] ?? null);
    $aCrn = $m['away_corners_avg'] ?? ($m['avg_corners_away'] ?? null);
    $crnPot = $m['corners_o85_potential'] ?? null;
    $cornersStr = '';
    if ($hCrn || $aCrn) {
        $cornersStr = $nl . "CORNERS_AVG: {$home}=" . ($hCrn ? round($hCrn,1) : 'n/a') . " | {$away}=" . ($aCrn ? round($aCrn,1) : 'n/a');
        if ($crnPot) $cornersStr .= " | O8.5_potential=" . round($crnPot,1) . '%';
    }

    // --- Cartons ---
    $hCrd = $m['home_cards_avg'] ?? null;
    $aCrd = $m['away_cards_avg'] ?? null;
    $cardsStr = ($hCrd || $aCrd)
        ? $nl . "CARDS_AVG: {$home}=" . ($hCrd ? round($hCrd,1) : 'n/a') . " | {$away}=" . ($aCrd ? round($aCrd,1) : 'n/a')
        : '';

    // --- Scored first ---
    $hSf = isset($m['home_scored_first_percentage']) ? round($m['home_scored_first_percentage'],1).'%' : 'n/a';
    $aSf = isset($m['away_scored_first_percentage']) ? round($m['away_scored_first_percentage'],1).'%' : 'n/a';
    $sfStr = $nl . "SCORED_FIRST: {$home}={$hSf} | {$away}={$aSf}";

    // --- Prediction FootyStats ---
    $pred = '';
    if (isset($m['predicted_winner'])) {
        $ps = ($m['predicted_home_goals'] ?? '?') . '-' . ($m['predicted_away_goals'] ?? '?');
        $pred = $nl . "FOOTYSTATS_PRED: winner=" . $m['predicted_winner'] . " score=" . $ps;
    }

    // --- Assemblage final ---
    $league = $m['league_name'] ?? ($m['competition'] ?? 'Unknown');
    $matchDate = isset($m['date_unix']) ? date('d/m H:i', $m['date_unix']) . ' (Paris)' : 'n/a';

    return "[FOOTYSTATS_COMPACT]"
        . $nl . "MATCH: {$home} vs {$away} | {$league} | {$matchDate}"
        . $formStr
        . $ppgStr
        . $xgStr
        . $goalsStr
        . $ouStr
        . $bttsStr
        . $csStr
        . $cornersStr
        . $cardsStr
        . $sfStr
        . $pred
        . $nl . "[/FOOTYSTATS_COMPACT]";
}

/**
 * Formate les stats saisonnières d'une équipe (action team_stats).
 */
function formatTeamStatsForClaude($t) {
    if (!$t || !is_array($t)) return "[TEAM_STATS: données indisponibles]";
    $nl   = "\n";
    $name = $t['name'] ?? 'Équipe';

    $safe = function($key, $round = 2) use ($t) {
        $v = $t[$key] ?? null;
        if ($v === null || $v === '') return 'n/a';
        return is_numeric($v) ? round((float)$v, $round) : $v;
    };

    // Stats offensives
    $gpg   = $safe('overall_home_goals_per_game') ?: $safe('goals_per_game');
    $xgpg  = $safe('xg_per_game') ?: $safe('xg');
    $shots = $safe('shots_per_game');
    $sot   = $safe('shots_on_target_per_game');

    // Stats défensives
    $cgpg  = $safe('overall_home_conceded_per_game') ?: $safe('conceded_per_game');
    $cs    = $safe('clean_sheet_percentage');
    $btts  = $safe('btts_percentage');

    // Over rates
    $o15 = $safe('over_15_percentage');
    $o25 = $safe('over_25_percentage');
    $o35 = $safe('over_35_percentage');

    // Forme
    $form  = $safe('form_run_5') ?: $safe('form');
    $ppg   = $safe('points_per_game');

    return "[TEAM_STATS: {$name}]"
        . $nl . "Forme_5={$form} | PPG={$ppg}"
        . $nl . "OFF: GF/match={$gpg} | xG/match={$xgpg} | Shots={$shots} | SOT={$sot}"
        . $nl . "DEF: GA/match={$cgpg} | CS%={$cs}"
        . $nl . "BTTS%={$btts} | O1.5%={$o15} | O2.5%={$o25} | O3.5%={$o35}"
        . $nl . "[/TEAM_STATS]";
}

function outputJson($data) {
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
