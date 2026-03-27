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
 * USAGE :
 *   ?action=today                                → matchs du jour avec stats
 *   ?action=league&id=LEAGUE_ID                  → stats de la ligue
 *   ?action=team&id=TEAM_ID                      → stats d'une équipe
 *   ?action=match&id=MATCH_ID                    → stats pré-match détaillées
 *   ?action=h2h&home=TEAM_ID&away=TEAM_ID        → head-to-head
 *   ?action=leagues                              → liste des ligues dispo
 *   ?action=search&q=arsenal                     → chercher une équipe
 * 
 * AUTH : token stratedge2026
 */

// ============================================
// CONFIG
// ============================================
$configFile = __DIR__ . '/config-keys.php';
if (file_exists($configFile)) { require_once $configFile; }
$FOOTYSTATS_KEY = defined('FOOTYSTATS_API_KEY') ? FOOTYSTATS_API_KEY : "1631907a095ad0953000398757257d07713f977696d039fca8a854b8f0be8ca5";
$AUTH_TOKEN = "stratedge2026";
$BASE_URL = "https://api.footystats.org";

// ============================================
// HEADERS
// ============================================
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Cache-Control: public, max-age=600"); // cache 10 min

// ============================================
// AUTH CHECK
// ============================================
$token = $_GET['token'] ?? $_SERVER['HTTP_X_STRATEDGE_TOKEN'] ?? '';
if ($token !== $AUTH_TOKEN) {
    http_response_code(403);
    echo json_encode(["error" => "Token invalide. Ajoute ?token=stratedge2026"]);
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

function outputJson($data) {
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
