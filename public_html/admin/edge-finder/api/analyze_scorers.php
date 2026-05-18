<?php
/**
 * STRATEDGE - Edge Finder - Analyse buteurs probables via Claude Sonnet 4.6
 * =======================================================================
 * POST /panel-x9k3m/edge-finder/api/analyze_scorers.php
 * Body: { "match_id": N, "force_refresh": false }
 *
 * Workflow :
 * 1. Si analyse en cache et !force_refresh -> retourne cache
 * 2. Sinon : recupere context match (equipes, ligue, lambda DC, cotes)
 * 3. Construit prompt structure
 * 4. Appelle Anthropic API (Claude Sonnet 4.6)
 * 5. Parse JSON reponse, stocke en cache, retourne
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../config-keys.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isSuperAdmin()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated (super-admin required)']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

$body = json_decode(file_get_contents('php://input'), true);
$match_id = (int)($body['match_id'] ?? 0);
$force_refresh = !empty($body['force_refresh']);
$peek_only = !empty($body['peek']);  // Si true, ne lance PAS l'analyse, retourne juste cache ou 404

if ($match_id <= 0) {
    http_response_code(400);
    die(json_encode(['error' => 'match_id required']));
}

// ===== 1. Cache check =====
if (!$force_refresh) {
    $cached = SE_Db::queryOne(
        "SELECT * FROM match_scorer_analysis WHERE match_id = ?",
        [$match_id]
    );
    if ($cached) {
        echo json_encode([
            'cached' => true,
            'generated_at' => $cached['generated_at'],
            'scorer_1' => [
                'name' => $cached['scorer_1_name'],
                'team' => $cached['scorer_1_team'],
                'confidence' => $cached['scorer_1_conf'],
                'reasoning' => $cached['scorer_1_reason'],
            ],
            'scorer_2' => [
                'name' => $cached['scorer_2_name'],
                'team' => $cached['scorer_2_team'],
                'confidence' => $cached['scorer_2_conf'],
                'reasoning' => $cached['scorer_2_reason'],
            ],
            'warnings' => json_decode($cached['warnings_json'] ?? '[]', true),
            'freshness_note' => $cached['freshness_note'],
            'model_used' => $cached['model_used'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Si peek_only et pas de cache, on s'arrete la (pas d'appel Claude)
if ($peek_only) {
    http_response_code(404);
    echo json_encode(['cached' => false, 'message' => 'No cache yet']);
    exit;
}

// ===== 2. Recupere context match =====
$match = SE_Db::queryOne(
    "SELECT m.home_name, m.away_name, m.kickoff_utc, m.lambda_home, m.lambda_away,
            m.league_name, m.league_country AS country
     FROM pick_matches m
     WHERE m.match_id = ?",
    [$match_id]
);

if (!$match) {
    http_response_code(404);
    die(json_encode(['error' => 'Match not found in pick_matches']));
}

// Recupere les cotes Over 2.5 / 3.5 du best candidate du match
$over25 = SE_Db::queryOne(
    "SELECT odds, model_proba, devig_proba
     FROM pick_candidates
     WHERE match_id = ? AND market = 'Over 2.5'
     ORDER BY ev DESC LIMIT 1",
    [$match_id]
);
$over35 = SE_Db::queryOne(
    "SELECT odds, model_proba, devig_proba
     FROM pick_candidates
     WHERE match_id = ? AND market = 'Over 3.5'
     ORDER BY ev DESC LIMIT 1",
    [$match_id]
);

// ===== 3. Build prompt =====
$lambda_home = (float)($match['lambda_home'] ?? 1.4);
$lambda_away = (float)($match['lambda_away'] ?? 1.2);
$lambda_total = $lambda_home + $lambda_away;

$kickoff = (new DateTime($match['kickoff_utc']))->format('d/m/Y H:i');

$prompt_parts = [
    "Tu es un analyste expert en paris sportifs sur le football.",
    "",
    "MATCH A ANALYSER :",
    "- Equipes : {$match['home_name']} (domicile) vs {$match['away_name']} (exterieur)",
    "- Ligue : " . ($match['league_name'] ?? 'inconnue') . " (" . ($match['country'] ?? '') . ")",
    "- Date : $kickoff",
    "",
    "MODELE STATISTIQUE (Dixon-Coles) :",
    "- Buts attendus domicile : " . number_format($lambda_home, 2),
    "- Buts attendus exterieur : " . number_format($lambda_away, 2),
    "- Total attendu : " . number_format($lambda_total, 2),
    "",
];

if ($over25) {
    $prompt_parts[] = "COTE MARCHE Over 2.5 : " . number_format((float)$over25['odds'], 2)
        . " (proba modele " . round((float)$over25['model_proba'] * 100) . "%)";
}
if ($over35) {
    $prompt_parts[] = "COTE MARCHE Over 3.5 : " . number_format((float)$over35['odds'], 2)
        . " (proba modele " . round((float)$over35['model_proba'] * 100) . "%)";
}

$prompt_parts[] = "";
$prompt_parts[] = "MISSION :";
$prompt_parts[] = "A partir de tes connaissances sur ces equipes (dont la saison en cours autant que tu en aies connaissance), identifie LES 2 BUTEURS LES PLUS PROBABLES pour ce match.";
$prompt_parts[] = "";
$prompt_parts[] = "Pour chaque buteur :";
$prompt_parts[] = "- name : prenom + nom du joueur";
$prompt_parts[] = "- team : 'home' ou 'away'";
$prompt_parts[] = "- confidence : 'Forte', 'Moyenne' ou 'Faible'";
$prompt_parts[] = "- reasoning : justification 3-4 phrases (role, finition, penos, contexte, forme habituelle)";
$prompt_parts[] = "";
$prompt_parts[] = "Liste aussi des WARNINGS : blessures que tu connais, suspensions, departs recents, ou tout flag pertinent.";
$prompt_parts[] = "";
$prompt_parts[] = "REPONDS UNIQUEMENT en JSON valide (rien d'autre, pas de markdown, pas de prefixe) :";
$prompt_parts[] = '{';
$prompt_parts[] = '  "scorer_1": {"name": "...", "team": "home", "confidence": "Forte", "reasoning": "..."},';
$prompt_parts[] = '  "scorer_2": {"name": "...", "team": "away", "confidence": "Moyenne", "reasoning": "..."},';
$prompt_parts[] = '  "warnings": ["...", "..."],';
$prompt_parts[] = '  "freshness_note": "Mes connaissances datent de XX. Verifier compositions et blessures actuelles."';
$prompt_parts[] = '}';

$prompt = implode("\n", $prompt_parts);

// ===== 4. Appel Anthropic =====
if (!defined('ANTHROPIC_API_KEY') || !ANTHROPIC_API_KEY) {
    http_response_code(503);
    die(json_encode(['error' => 'ANTHROPIC_API_KEY non configure']));
}

$payload = [
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1500,
    'messages' => [
        ['role' => 'user', 'content' => $prompt]
    ],
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'x-api-key: ' . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01',
        'content-type: application/json',
    ],
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($response === false || $http_code !== 200) {
    http_response_code(502);
    die(json_encode([
        'error' => 'Anthropic API failed',
        'http_code' => $http_code,
        'detail' => $err ?: substr((string)$response, 0, 500),
    ]));
}

$data = json_decode($response, true);
$text_response = $data['content'][0]['text'] ?? '';
$usage = $data['usage'] ?? [];
$tokens_in = (int)($usage['input_tokens'] ?? 0);
$tokens_out = (int)($usage['output_tokens'] ?? 0);
// Pricing Sonnet 4.6 : $3/M input, $15/M output
$cost = ($tokens_in / 1000000.0) * 3 + ($tokens_out / 1000000.0) * 15;

// Parse JSON (le modele renvoie parfois du markdown ```json autour)
$json_str = $text_response;
if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $text_response, $m)) {
    $json_str = $m[1];
}
$parsed = json_decode(trim($json_str), true);

if (!$parsed || !isset($parsed['scorer_1']) || !isset($parsed['scorer_2'])) {
    http_response_code(502);
    die(json_encode([
        'error' => 'Reponse Claude invalide',
        'raw' => substr($text_response, 0, 1000),
    ]));
}

// ===== 5. Cache en DB =====
// Reconnexion : l'appel Claude a pu durer assez longtemps pour que MySQL
// ferme la connexion inactive.
SE_Db::reconnect();

SE_Db::execute(
    "INSERT INTO match_scorer_analysis
     (match_id, scorer_1_name, scorer_1_team, scorer_1_conf, scorer_1_reason,
      scorer_2_name, scorer_2_team, scorer_2_conf, scorer_2_reason,
      warnings_json, freshness_note, raw_response, model_used,
      tokens_input, tokens_output, cost_usd)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
       scorer_1_name = VALUES(scorer_1_name),
       scorer_1_team = VALUES(scorer_1_team),
       scorer_1_conf = VALUES(scorer_1_conf),
       scorer_1_reason = VALUES(scorer_1_reason),
       scorer_2_name = VALUES(scorer_2_name),
       scorer_2_team = VALUES(scorer_2_team),
       scorer_2_conf = VALUES(scorer_2_conf),
       scorer_2_reason = VALUES(scorer_2_reason),
       warnings_json = VALUES(warnings_json),
       freshness_note = VALUES(freshness_note),
       raw_response = VALUES(raw_response),
       tokens_input = VALUES(tokens_input),
       tokens_output = VALUES(tokens_output),
       cost_usd = VALUES(cost_usd),
       generated_at = NOW()",
    [
        $match_id,
        $parsed['scorer_1']['name'] ?? 'Unknown',
        $parsed['scorer_1']['team'] ?? 'home',
        $parsed['scorer_1']['confidence'] ?? 'Moyenne',
        $parsed['scorer_1']['reasoning'] ?? '',
        $parsed['scorer_2']['name'] ?? 'Unknown',
        $parsed['scorer_2']['team'] ?? 'away',
        $parsed['scorer_2']['confidence'] ?? 'Moyenne',
        $parsed['scorer_2']['reasoning'] ?? '',
        json_encode($parsed['warnings'] ?? [], JSON_UNESCAPED_UNICODE),
        $parsed['freshness_note'] ?? null,
        $text_response,
        'claude-sonnet-4-6',
        $tokens_in,
        $tokens_out,
        round($cost, 4),
    ]
);

echo json_encode([
    'cached' => false,
    'generated_at' => date('Y-m-d H:i:s'),
    'scorer_1' => $parsed['scorer_1'],
    'scorer_2' => $parsed['scorer_2'],
    'warnings' => $parsed['warnings'] ?? [],
    'freshness_note' => $parsed['freshness_note'] ?? null,
    'model_used' => 'claude-sonnet-4-6',
    'cost_usd' => round($cost, 4),
    'tokens' => ['input' => $tokens_in, 'output' => $tokens_out],
], JSON_UNESCAPED_UNICODE);
