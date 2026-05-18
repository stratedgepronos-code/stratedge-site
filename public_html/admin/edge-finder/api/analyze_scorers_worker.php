<?php
/**
 * STRATEDGE - Edge Finder - WORKER analyse buteurs (asynchrone)
 * ================================================================================
 *
 * Ce script fait l'analyse longue (recherches web + redaction JSON) et ecrit
 * le resultat dans match_scorer_analysis. Il ne produit AUCUNE sortie pour le
 * navigateur : il tourne en tache de fond, declenche par analyze_scorers_start.php.
 *
 * Appel interne uniquement (pas d'auth super-admin : protege par un token
 * partage genere par le start). Param :
 *   GET ?match_id=N&worker_token=XXX
 *
 * Cycle de vie du job dans match_scorer_analysis.status :
 *   pending  -> running -> done   (succes)
 *                       -> error  (echec)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../config-keys.php';
require_once __DIR__ . '/../lib/db.php';

// Pas de sortie navigateur : le worker tourne en fond.
ignore_user_abort(true);
@set_time_limit(300);

$match_id = (int)($_GET['match_id'] ?? 0);
$worker_token = (string)($_GET['worker_token'] ?? '');

// Auth interne : le token doit correspondre a celui defini en config.
// (SE_WORKER_TOKEN : constante partagee, ajoutee a config.php)
if (!defined('SE_WORKER_TOKEN') || !hash_equals(SE_WORKER_TOKEN, $worker_token)) {
    http_response_code(403);
    exit('forbidden');
}
if ($match_id <= 0) {
    http_response_code(400);
    exit('match_id required');
}

/**
 * Marque le job en erreur et termine.
 */
function worker_fail(int $match_id, string $msg): void {
    try {
        SE_Db::reconnect();
        SE_Db::execute(
            "UPDATE match_scorer_analysis SET status = 'error', error_msg = ? WHERE match_id = ?",
            [mb_substr($msg, 0, 490), $match_id]
        );
    } catch (Throwable $e) {
        // best effort
    }
    exit;
}

// ===== Marque le job 'running' =====
SE_Db::execute(
    "UPDATE match_scorer_analysis SET status = 'running', started_at = NOW() WHERE match_id = ?",
    [$match_id]
);

// ===== Recupere context match =====
$match = SE_Db::queryOne(
    "SELECT m.home_name, m.away_name, m.kickoff_utc, m.lambda_home, m.lambda_away,
            m.league_name, m.league_country AS country
     FROM pick_matches m
     WHERE m.match_id = ?",
    [$match_id]
);
if (!$match) {
    worker_fail($match_id, 'Match introuvable dans pick_matches');
}

// Cotes Over 2.5 / 3.5 / BTTS
$over25 = SE_Db::queryOne(
    "SELECT odds, model_proba FROM pick_candidates WHERE match_id = ? AND market = 'Over 2.5' ORDER BY ev DESC LIMIT 1",
    [$match_id]
);
$over35 = SE_Db::queryOne(
    "SELECT odds, model_proba FROM pick_candidates WHERE match_id = ? AND market = 'Over 3.5' ORDER BY ev DESC LIMIT 1",
    [$match_id]
);
$btts = SE_Db::queryOne(
    "SELECT odds, model_proba FROM pick_candidates WHERE match_id = ? AND market = 'BTTS Yes' ORDER BY ev DESC LIMIT 1",
    [$match_id]
);

$market_odds_lines = [];
if ($over25) $market_odds_lines[] = "  - Over 2.5 buts : cote " . number_format((float)$over25['odds'], 2) . " (modele " . round((float)$over25['model_proba']*100) . "%)";
if ($over35) $market_odds_lines[] = "  - Over 3.5 buts : cote " . number_format((float)$over35['odds'], 2) . " (modele " . round((float)$over35['model_proba']*100) . "%)";
if ($btts)   $market_odds_lines[] = "  - BTTS Yes : cote " . number_format((float)$btts['odds'], 2) . " (modele " . round((float)$btts['model_proba']*100) . "%)";
$market_odds = implode("\n", $market_odds_lines) ?: "  (cotes non dispo en DB)";

// ===== Charge le prompt =====
$prompt_path = __DIR__ . '/../prompts/PROMPT_BUTEURS_v1_1.md';
if (!file_exists($prompt_path)) {
    worker_fail($match_id, 'Fichier prompt introuvable');
}
$prompt_template = file_get_contents($prompt_path);

$lambda_home = (float)($match['lambda_home'] ?? 1.4);
$lambda_away = (float)($match['lambda_away'] ?? 1.2);
$lambda_total = $lambda_home + $lambda_away;
$kickoff_paris = (new DateTime($match['kickoff_utc']))->format('d/m/Y H:i');

$prompt = strtr($prompt_template, [
    '{{home_name}}' => $match['home_name'],
    '{{away_name}}' => $match['away_name'],
    '{{league_name}}' => $match['league_name'] ?? 'inconnue',
    '{{country}}' => $match['country'] ?? '',
    '{{kickoff_paris}}' => $kickoff_paris,
    '{{lambda_home}}' => number_format($lambda_home, 2),
    '{{lambda_away}}' => number_format($lambda_away, 2),
    '{{lambda_total}}' => number_format($lambda_total, 2),
    '{{market_odds}}' => $market_odds,
]);

// Budget recherche : 4 max (le worker a aussi la limite serveur ~120s)
$search_budget = "⚠️ BUDGET RECHERCHE STRICT : tu as droit a 4 recherches web MAXIMUM. "
    . "Utilise-les sur l'ESSENTIEL en priorite : (1) compos probables + blessures/suspensions des 2 equipes, "
    . "(2) stats du buteur le plus probable de l'equipe favorite, (3) stats du buteur le plus probable de l'autre equipe, "
    . "(4) une recherche libre selon le besoin. Ne gaspille AUCUNE recherche. "
    . "Apres 4 recherches, tu DOIS rediger directement le JSON final complet sans recherche supplementaire.\n\n";
$prompt = $search_budget . $prompt;

// ===== Appel Anthropic (non-stream : le worker n'a personne a qui streamer) =====
if (!defined('ANTHROPIC_API_KEY') || !ANTHROPIC_API_KEY) {
    worker_fail($match_id, 'ANTHROPIC_API_KEY non configure');
}

$payload = [
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 8000,
    'tools' => [
        ['type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 4]
    ],
    'messages' => [
        ['role' => 'user', 'content' => $prompt]
    ],
];

$start_time = microtime(true);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 280,
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
$curl_err = curl_error($ch);
curl_close($ch);

if ($http_code !== 200 || !$response) {
    worker_fail($match_id, "Anthropic API HTTP $http_code : " . mb_substr($curl_err ?: (string)$response, 0, 300));
}

$resp = json_decode($response, true);
if (!is_array($resp)) {
    worker_fail($match_id, 'Reponse Anthropic non parsable');
}

// ===== Extrait le texte + compte les recherches web =====
$accumulated_text = '';
$searches_done = [];
foreach (($resp['content'] ?? []) as $block) {
    $btype = $block['type'] ?? '';
    if ($btype === 'text') {
        $accumulated_text .= $block['text'] ?? '';
    } elseif ($btype === 'server_tool_use' && ($block['name'] ?? '') === 'web_search') {
        $q = $block['input']['query'] ?? 'recherche';
        $searches_done[] = ['query' => $q, 'ts' => date('H:i:s')];
    }
}

$tokens_in = (int)($resp['usage']['input_tokens'] ?? 0);
$tokens_out = (int)($resp['usage']['output_tokens'] ?? 0);
$web_search_count = count($searches_done);
$duration = (int)round(microtime(true) - $start_time);

// ===== Parse le JSON final =====
$json_str = trim($accumulated_text);
if (preg_match('/```(?:json)?\s*(\{.+\})\s*```/s', $json_str, $mm)) {
    $json_str = $mm[1];
}
$first_brace = strpos($json_str, '{');
$last_brace = strrpos($json_str, '}');
if ($first_brace !== false && $last_brace !== false && $last_brace > $first_brace) {
    $json_str = substr($json_str, $first_brace, $last_brace - $first_brace + 1);
}

$parsed = json_decode($json_str, true);
if (!$parsed || !isset($parsed['scorers']) || !is_array($parsed['scorers']) || count($parsed['scorers']) === 0) {
    $looks_truncated = (strlen($accumulated_text) > 200)
        && (substr_count($json_str, '{') > substr_count($json_str, '}'));
    worker_fail($match_id, $looks_truncated
        ? 'Analyse interrompue (reponse tronquee) - relance l\'analyse'
        : 'Reponse Claude invalide (' . json_last_error_msg() . ')');
}

// Pricing Sonnet 4.6 : $3 input / $15 output par MTok
$cost = ($tokens_in / 1_000_000.0) * 3 + ($tokens_out / 1_000_000.0) * 15;

// ===== Ecrit le resultat, status 'done' =====
SE_Db::reconnect();
SE_Db::execute(
    "UPDATE match_scorer_analysis SET
       status = 'done',
       error_msg = NULL,
       scorers_json = ?,
       markdown_full = ?,
       warnings_json = ?,
       freshness_note = ?,
       searches_json = ?,
       raw_response = ?,
       model_used = ?,
       tokens_input = ?,
       tokens_output = ?,
       web_searches_count = ?,
       cost_usd = ?,
       duration_seconds = ?,
       generated_at = NOW()
     WHERE match_id = ?",
    [
        json_encode($parsed['scorers'], JSON_UNESCAPED_UNICODE),
        $parsed['markdown_full'] ?? null,
        json_encode($parsed['warnings'] ?? [], JSON_UNESCAPED_UNICODE),
        $parsed['freshness_note'] ?? null,
        json_encode($searches_done, JSON_UNESCAPED_UNICODE),
        $accumulated_text,
        'claude-sonnet-4-6',
        $tokens_in,
        $tokens_out,
        $web_search_count,
        round($cost, 4),
        $duration,
        $match_id,
    ]
);

exit('ok');
