<?php
/**
 * STRATEDGE — FOOTYSTATS API PROXY (MINIMAL)
 * ============================================
 * Proxy ultra-léger vers api.football-data-api.com
 * Cache la clé FootyStats côté serveur.
 * 
 * USAGE :
 *   ?action=today                    → matchs du jour
 *   ?action=today&date=2026-04-03    → matchs d'une date
 *   ?action=league&id=LEAGUE_ID      → matchs d'une ligue
 *   ?action=team&id=TEAM_ID          → stats équipe
 *   ?action=match&id=MATCH_ID        → détails match
 *   ?action=leagues                  → liste ligues
 *   ?action=search&q=arsenal         → chercher équipe
 *   ?action=h2h&home=ID&away=ID      → head-to-head
 * 
 * AUTH : token dans config-keys.php (AUTH_TOKEN)
 */

// Config
$configFile = __DIR__ . '/config-keys.php';
if (file_exists($configFile)) { require_once $configFile; }

if (!defined('FOOTYSTATS_API_KEY') || !defined('AUTH_TOKEN')) {
    http_response_code(503);
    die(json_encode(["error" => "Config manquante."]));
}

// Headers
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: https://stratedgepronos.fr");
header("Cache-Control: public, max-age=600");

// Auth
$token = $_GET['token'] ?? $_SERVER['HTTP_X_STRATEDGE_TOKEN'] ?? '';
if (!hash_equals(AUTH_TOKEN, $token)) {
    http_response_code(403);
    die(json_encode(["error" => "Accès non autorisé."]));
}

// Routes
$key = FOOTYSTATS_API_KEY;
$base = "https://api.football-data-api.com";
$action = $_GET['action'] ?? 'today';

switch ($action) {
    case 'today':
        $date = $_GET['date'] ?? date('Y-m-d');
        $url = "$base/todays-matches?key=$key&date=$date";
        break;
    case 'league':
        $id = $_GET['id'] ?? '';
        $url = "$base/league-matches?key=$key&league_id=$id";
        break;
    case 'team':
        $id = $_GET['id'] ?? '';
        $url = "$base/league-teams?key=$key&league_id=$id";
        break;
    case 'match':
        $id = $_GET['id'] ?? '';
        $url = "$base/match?key=$key&match_id=$id";
        break;
    case 'leagues':
        $url = "$base/league-list?key=$key&chosen_leagues_only=true";
        break;
    case 'search':
        $q = $_GET['q'] ?? '';
        $url = "$base/league-teams?key=$key&team_name=" . urlencode($q);
        break;
    case 'h2h':
        $home = $_GET['home'] ?? '';
        $away = $_GET['away'] ?? '';
        $url = "$base/head-to-head?key=$key&team_a_id=$home&team_b_id=$away";
        break;
    default:
        die(json_encode(["error" => "Action inconnue: $action"]));
}

// Fetch
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_USERAGENT => 'StratEdge-Proxy/1.0',
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(502);
    die(json_encode(["error" => "Erreur API FootyStats", "details" => $error]));
}

http_response_code($httpCode);
echo $response;
