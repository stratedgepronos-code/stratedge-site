<?php
/**
 * STRATEDGE — API-FOOTBALL PROXY
 * ================================
 * Proxy vers v3.football.api-sports.io
 * Cache la cle API-Football cote serveur.
 * Permet de contourner la whitelist IP variable (PC perso, mobile, etc.)
 * en utilisant l'IP fixe Hostinger pour les appels API.
 *
 * USAGE :
 *   ?endpoint=status                            -> quota / abonnement
 *   ?endpoint=players&team=106&season=2025      -> joueurs d'une equipe
 *   ?endpoint=players&id=12345&season=2025      -> profil joueur
 *   ?endpoint=fixtures&team=106&season=2025     -> fixtures d'une equipe
 *   ?endpoint=fixtures/players&fixture=1234567  -> stats joueurs d'un match
 *   ?endpoint=fixtures/headtohead&h2h=106-95&season=2025
 *   ?endpoint=teams&search=brest                -> chercher equipe
 *
 * AUTH : token dans config-keys.php (AUTH_TOKEN)
 *        cle API-Football dans API_FOOTBALL_KEY (a ajouter dans config-keys.php)
 */

declare(strict_types=1);

// Config
$configFile = __DIR__ . '/config-keys.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

if (!defined('AUTH_TOKEN')) {
    http_response_code(503);
    die(json_encode(["error" => "Config manquante : AUTH_TOKEN."]));
}

if (!defined('API_FOOTBALL_KEY')) {
    http_response_code(503);
    die(json_encode([
        "error" => "Config manquante : API_FOOTBALL_KEY a ajouter dans config-keys.php",
        "fix" => "Ajouter : define('API_FOOTBALL_KEY', 'YOUR_32CHAR_KEY');"
    ]));
}

// Headers de reponse
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: https://stratedgepronos.fr");
header("Cache-Control: public, max-age=3600");  // cache 1h cote client

// Auth
$token = $_GET['token'] ?? $_SERVER['HTTP_X_STRATEDGE_TOKEN'] ?? '';
if (!hash_equals(AUTH_TOKEN, $token)) {
    http_response_code(403);
    die(json_encode(["error" => "Acces non autorise."]));
}

// Endpoint demande
$endpoint = $_GET['endpoint'] ?? '';
if (!$endpoint) {
    http_response_code(400);
    die(json_encode([
        "error" => "Parametre 'endpoint' requis.",
        "examples" => [
            "?endpoint=status",
            "?endpoint=players&team=106&season=2025",
            "?endpoint=fixtures&team=106&season=2025",
        ],
    ]));
}

// Whitelist d'endpoints pour eviter abus
$allowed_endpoints = [
    'status', 'timezone',
    'players', 'players/squads', 'players/profiles', 'players/seasons',
    'teams', 'teams/statistics', 'teams/seasons',
    'fixtures', 'fixtures/players', 'fixtures/statistics', 'fixtures/headtohead',
    'leagues', 'standings', 'transfers', 'trophies', 'coachs', 'predictions',
    'odds', 'sidelined',
];
if (!in_array($endpoint, $allowed_endpoints, true)) {
    http_response_code(400);
    die(json_encode([
        "error" => "Endpoint non autorise.",
        "endpoint" => $endpoint,
        "allowed" => $allowed_endpoints,
    ]));
}

// Build query string (tous les params sauf endpoint, token)
$params = $_GET;
unset($params['endpoint'], $params['token']);
$query = http_build_query($params);

// Construire URL
$base = "https://v3.football.api-sports.io";
$url = "$base/$endpoint" . ($query ? "?$query" : "");

// Appel HTTP
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        "x-apisports-key: " . API_FOOTBALL_KEY,
        "Accept: application/json",
    ],
    CURLOPT_USERAGENT      => "StratEdge-EdgeFinder/1.0 (Hostinger; +https://stratedgepronos.fr)",
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    die(json_encode([
        "error" => "Bad gateway",
        "detail" => $err,
        "url" => $url,
    ]));
}

// Renvoie la reponse upstream telle quelle
http_response_code($http_code);
echo $response;
