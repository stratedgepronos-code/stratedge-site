<?php
/**
 * STRATEDGE ODDS API PROXY
 * ========================
 * Proxy PHP pour The Odds API.
 * - Cache la clé API côté serveur
 * - Accessible par Claude via web_fetch
 * - Accessible par le scanner admin HTML
 * 
 * INSTALLATION : upload dans public_html/ sur Hostinger
 * URL : https://stratedgepronos.fr/odds-api.php
 * 
 * USAGE :
 *   ?action=sports                          → liste des sports dispo
 *   ?action=events&league=soccer_epl        → matchs à venir
 *   ?action=odds&league=soccer_epl&event=ID → cotes match (1X2, O/U, AH)
 *   ?action=props&league=soccer_epl&event=ID → props joueur (SOT, buteur, BTTS)
 *   ?action=scan&league=soccer_epl          → TOUT d'un coup (events + odds de chaque match)
 * 
 * AUTH : token simple pour éviter l'abus public
 *   ?token=stratedge2026 (ou header X-StratEdge-Token)
 */

// ============================================
// CONFIG
// ============================================
$ODDS_API_KEY = "2203e181d78187eafad87ae8f436ad53";
$AUTH_TOKEN = "stratedge2026"; // token simple pour auth
$BASE_URL = "https://api.the-odds-api.com/v4";

// ============================================
// CORS + HEADERS
// ============================================
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: X-StratEdge-Token");
header("Cache-Control: public, max-age=300"); // cache 5 min

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
$action = $_GET['action'] ?? 'sports';
$league = $_GET['league'] ?? 'soccer_epl';
$eventId = $_GET['event'] ?? '';
$regions = $_GET['regions'] ?? 'eu,uk';

switch ($action) {

    case 'sports':
        $url = "$BASE_URL/sports/?apiKey=$ODDS_API_KEY";
        $data = fetchApi($url);
        // Filtrer seulement les sports soccer actifs
        $soccer = array_filter($data, function($s) {
            return strpos($s['key'], 'soccer') !== false && $s['active'];
        });
        outputJson(array_values($soccer));
        break;

    case 'events':
        $url = "$BASE_URL/sports/$league/events?apiKey=$ODDS_API_KEY";
        $data = fetchApi($url);
        outputJson($data);
        break;

    case 'odds':
        $markets = $_GET['markets'] ?? 'h2h,totals,spreads';
        if ($eventId) {
            $url = "$BASE_URL/sports/$league/events/$eventId/odds?apiKey=$ODDS_API_KEY&regions=$regions&markets=$markets&oddsFormat=decimal";
        } else {
            $url = "$BASE_URL/sports/$league/odds?apiKey=$ODDS_API_KEY&regions=$regions&markets=$markets&oddsFormat=decimal";
        }
        $data = fetchApi($url);
        outputJson($data);
        break;

    case 'props':
        $markets = $_GET['markets'] ?? 'player_shots_on_target,player_goal_scorer_anytime,btts,team_totals,double_chance';
        if (!$eventId) {
            outputJson(["error" => "event ID requis. Ex: ?action=props&league=soccer_epl&event=abc123"]);
            break;
        }
        $url = "$BASE_URL/sports/$league/events/$eventId/odds?apiKey=$ODDS_API_KEY&regions=$regions,us&markets=$markets&oddsFormat=decimal";
        $data = fetchApi($url);
        outputJson($data);
        break;

    case 'scan':
        // Mode scan complet : récupère events + odds de chaque match
        $eventsUrl = "$BASE_URL/sports/$league/events?apiKey=$ODDS_API_KEY";
        $events = fetchApi($eventsUrl);
        
        if (!is_array($events) || count($events) === 0) {
            outputJson(["error" => "Aucun match trouvé pour $league", "events" => []]);
            break;
        }

        // Récupérer les odds globales (tous les matchs en un call)
        $markets = $_GET['markets'] ?? 'h2h,totals';
        $oddsUrl = "$BASE_URL/sports/$league/odds?apiKey=$ODDS_API_KEY&regions=$regions&markets=$markets&oddsFormat=decimal";
        $oddsData = fetchApi($oddsUrl);

        // Construire le résultat enrichi
        $result = [
            "league" => $league,
            "scanned_at" => date("Y-m-d H:i:s T"),
            "matches_count" => count($events),
            "matches" => []
        ];

        // Mapper les odds par event ID
        $oddsMap = [];
        if (is_array($oddsData)) {
            foreach ($oddsData as $match) {
                $oddsMap[$match['id']] = $match;
            }
        }

        foreach (array_slice($events, 0, 15) as $ev) {
            $matchData = [
                "id" => $ev['id'],
                "home" => $ev['home_team'],
                "away" => $ev['away_team'],
                "kickoff" => $ev['commence_time'],
                "odds" => []
            ];

            if (isset($oddsMap[$ev['id']])) {
                $matchOdds = $oddsMap[$ev['id']];
                foreach ($matchOdds['bookmakers'] ?? [] as $bk) {
                    foreach ($bk['markets'] ?? [] as $mkt) {
                        foreach ($mkt['outcomes'] ?? [] as $oc) {
                            $key = $mkt['key'];
                            $label = $oc['name'] . (isset($oc['point']) ? " " . $oc['point'] : "");
                            if (!isset($matchData['odds'][$key])) {
                                $matchData['odds'][$key] = [];
                            }
                            // Garder la meilleure cote par outcome
                            if (!isset($matchData['odds'][$key][$label]) || 
                                $oc['price'] > $matchData['odds'][$key][$label]['price']) {
                                $matchData['odds'][$key][$label] = [
                                    "price" => $oc['price'],
                                    "bookmaker" => $bk['title'],
                                    "implied_pct" => round(100 / $oc['price'], 1)
                                ];
                            }
                        }
                    }
                }
            }

            $result['matches'][] = $matchData;
        }

        outputJson($result);
        break;

    default:
        outputJson([
            "error" => "Action inconnue: $action",
            "actions_disponibles" => [
                "sports" => "Liste des ligues disponibles",
                "events" => "Matchs à venir (params: league)",
                "odds" => "Cotes match (params: league, event, markets)",
                "props" => "Props joueur SOT/buteur (params: league, event)",
                "scan" => "Scan complet d'une ligue (params: league)"
            ]
        ]);
}

// ============================================
// HELPERS
// ============================================
function fetchApi($url) {
    $opts = [
        "http" => [
            "method" => "GET",
            "timeout" => 15,
            "header" => "Accept: application/json\r\n"
        ]
    ];
    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return ["error" => "Impossible de contacter The Odds API"];
    }
    
    // Extraire les headers de quota
    global $quotaHeaders;
    $quotaHeaders = [];
    if (isset($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (stripos($h, 'x-requests-remaining') !== false) {
                $quotaHeaders['remaining'] = trim(explode(':', $h, 2)[1] ?? '');
            }
            if (stripos($h, 'x-requests-used') !== false) {
                $quotaHeaders['used'] = trim(explode(':', $h, 2)[1] ?? '');
            }
        }
    }
    
    return json_decode($response, true);
}

function outputJson($data) {
    global $quotaHeaders;
    if (!empty($quotaHeaders)) {
        $data = is_array($data) ? $data : [$data];
        if (isset($data[0]) || isset($data['error']) || isset($data['matches'])) {
            // Ne pas modifier la structure si c'est un array indexé
        } else {
            $data['_quota'] = $quotaHeaders;
        }
    }
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
