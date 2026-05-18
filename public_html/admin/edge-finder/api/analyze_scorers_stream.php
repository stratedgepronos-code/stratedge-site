<?php
/**
 * STRATEDGE - Edge Finder - Analyse buteurs TOP 3 via Claude Opus 4.7 + Web Search
 * ================================================================================
 *
 * STREAM SSE (Server-Sent Events) :
 *   GET /panel-x9k3m/edge-finder/api/analyze_scorers_stream.php?match_id=N&force=0|1
 *
 * Events SSE emis :
 *   event: status         → {step: "phase0", message: "Recherche compos probables..."}
 *   event: web_search     → {query: "Brest compo probable", index: 0}
 *   event: tokens         → {input: 3200, output_so_far: 850}
 *   event: complete       → {scorers: [...], warnings: [...], markdown_full: "...", ...}
 *   event: error          → {message: "..."}
 *
 * Le frontend (EventSource) consomme ce stream et anime au fur et a mesure.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../config-keys.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../../../includes/auth.php';

if (!isSuperAdmin()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Not authenticated (super-admin required)']);
    exit;
}

// Headers SSE
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store');
header('X-Accel-Buffering: no');  // Disable nginx buffering
header('Connection: keep-alive');

// Pas de timeout PHP
@set_time_limit(120);
ignore_user_abort(false);

// Force flush apres chaque echo
@ob_end_flush();
ob_implicit_flush(true);

function sse_send(string $event, array $data): void {
    echo "event: $event\n";
    echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    if (function_exists('flush')) @flush();
}

function sse_error(string $msg, array $extra = []): void {
    sse_send('error', array_merge(['message' => $msg], $extra));
    exit;
}

// Params
$match_id = (int)($_GET['match_id'] ?? 0);
$force_refresh = !empty($_GET['force']);

if ($match_id <= 0) {
    sse_error('match_id required');
}

// ===== Check cache si pas force_refresh =====
if (!$force_refresh) {
    $cached = SE_Db::queryOne(
        "SELECT * FROM match_scorer_analysis WHERE match_id = ?",
        [$match_id]
    );
    if ($cached) {
        sse_send('status', ['step' => 'cache_hit', 'message' => 'Analyse en cache, restauration...']);
        usleep(300_000);
        sse_send('complete', [
            'cached' => true,
            'generated_at' => $cached['generated_at'],
            'scorers' => json_decode($cached['scorers_json'], true),
            'warnings' => json_decode($cached['warnings_json'] ?? '[]', true),
            'freshness_note' => $cached['freshness_note'],
            'markdown_full' => $cached['markdown_full'],
            'searches' => json_decode($cached['searches_json'] ?? '[]', true),
            'model_used' => $cached['model_used'],
            'tokens_input' => (int)$cached['tokens_input'],
            'tokens_output' => (int)$cached['tokens_output'],
            'cost_usd' => (float)$cached['cost_usd'],
            'web_searches_count' => (int)$cached['web_searches_count'],
        ]);
        exit;
    }
}

// ===== Recupere context match =====
sse_send('status', ['step' => 'context', 'message' => 'Chargement du contexte match...']);

$match = SE_Db::queryOne(
    "SELECT m.home_name, m.away_name, m.kickoff_utc, m.lambda_home, m.lambda_away,
            m.league_name, m.league_country AS country
     FROM pick_matches m
     WHERE m.match_id = ?",
    [$match_id]
);

if (!$match) {
    sse_error('Match not found in pick_matches');
}

// Cotes Over 2.5 / 3.5
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

// Charge le prompt
$prompt_path = __DIR__ . '/../prompts/PROMPT_BUTEURS_v1_1.md';
if (!file_exists($prompt_path)) {
    sse_error('Prompt file not found: ' . $prompt_path);
}
$prompt_template = file_get_contents($prompt_path);

// Variables
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

sse_send('status', ['step' => 'phase0', 'message' => 'Analyse PHASE 0 - Compos & blessures...']);

// ===== Appel Anthropic en streaming avec web_search =====
if (!defined('ANTHROPIC_API_KEY') || !ANTHROPIC_API_KEY) {
    sse_error('ANTHROPIC_API_KEY non configure');
}

$payload = [
    'model' => 'claude-opus-4-7',
    'max_tokens' => 8000,
    'stream' => true,
    'tools' => [
        ['type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 10]
    ],
    'messages' => [
        ['role' => 'user', 'content' => $prompt]
    ],
];

$start_time = microtime(true);
$buffer = '';
$accumulated_text = '';
$tokens_in = 0;
$tokens_out = 0;
$searches_done = [];
$web_search_count = 0;
$current_search_query = null;

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'x-api-key: ' . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01',
        'content-type: application/json',
        'accept: text/event-stream',
    ],
    CURLOPT_WRITEFUNCTION => function($ch, $chunk) use (
        &$buffer, &$accumulated_text, &$tokens_in, &$tokens_out,
        &$searches_done, &$web_search_count, &$current_search_query
    ) {
        $buffer .= $chunk;
        // Parse les SSE events Anthropic
        while (($pos = strpos($buffer, "\n\n")) !== false) {
            $event_block = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 2);
            $lines = explode("\n", $event_block);
            $event_type = null;
            $event_data = null;
            foreach ($lines as $line) {
                if (str_starts_with($line, 'event: ')) $event_type = substr($line, 7);
                elseif (str_starts_with($line, 'data: ')) $event_data = substr($line, 6);
            }
            if (!$event_type || !$event_data) continue;
            $data = json_decode($event_data, true);
            if (!is_array($data)) continue;

            // message_start : tokens input
            if ($event_type === 'message_start' && isset($data['message']['usage']['input_tokens'])) {
                $tokens_in = (int)$data['message']['usage']['input_tokens'];
                sse_send('tokens', ['input' => $tokens_in, 'output_so_far' => 0]);
            }
            // content_block_start : detection web_search
            elseif ($event_type === 'content_block_start' && isset($data['content_block']['type'])) {
                $ct = $data['content_block']['type'];
                if ($ct === 'server_tool_use' && ($data['content_block']['name'] ?? '') === 'web_search') {
                    // La query arrivera plus tard dans input_json_delta
                    $current_search_query = '';
                }
            }
            // input_json_delta : accumule la query du web_search
            elseif ($event_type === 'content_block_delta' && ($data['delta']['type'] ?? '') === 'input_json_delta') {
                $current_search_query .= ($data['delta']['partial_json'] ?? '');
            }
            // text_delta : accumule la reponse texte
            elseif ($event_type === 'content_block_delta' && ($data['delta']['type'] ?? '') === 'text_delta') {
                $accumulated_text .= ($data['delta']['text'] ?? '');
                $tokens_out++;  // Approximation
                if ($tokens_out % 50 === 0) {
                    sse_send('tokens', ['input' => $tokens_in, 'output_so_far' => $tokens_out * 3]);
                }
            }
            // content_block_stop : end of a block - si c'etait un web_search, on parse la query
            elseif ($event_type === 'content_block_stop' && $current_search_query !== null) {
                $query_data = json_decode($current_search_query, true);
                $query_text = $query_data['query'] ?? 'recherche en cours';
                $searches_done[] = ['query' => $query_text, 'ts' => date('H:i:s')];
                $web_search_count++;
                sse_send('web_search', ['query' => $query_text, 'index' => $web_search_count - 1]);
                $current_search_query = null;
            }
            // message_delta : tokens output final
            elseif ($event_type === 'message_delta' && isset($data['usage']['output_tokens'])) {
                $tokens_out = (int)$data['usage']['output_tokens'];
            }
            // message_stop : fin
            elseif ($event_type === 'message_stop') {
                // Pas d'action ici, on traite apres le curl_exec
            }
        }
        return strlen($chunk);
    },
]);

$result = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err = curl_error($ch);
curl_close($ch);

if ($http_code !== 200) {
    sse_error('Anthropic API failed', ['http_code' => $http_code, 'curl_error' => $curl_err]);
}

$duration = (int)round(microtime(true) - $start_time);
sse_send('status', ['step' => 'parse', 'message' => 'Analyse generee, parsing du JSON...']);

// ===== Parse le JSON final =====
$json_str = trim($accumulated_text);
// Strip eventuel markdown ```json ... ```
if (preg_match('/```(?:json)?\s*(\{.+\})\s*```/s', $json_str, $m)) {
    $json_str = $m[1];
}
// Strip prefixes hors JSON
$first_brace = strpos($json_str, '{');
$last_brace = strrpos($json_str, '}');
if ($first_brace !== false && $last_brace !== false && $last_brace > $first_brace) {
    $json_str = substr($json_str, $first_brace, $last_brace - $first_brace + 1);
}

$parsed = json_decode($json_str, true);
if (!$parsed || !isset($parsed['scorers']) || !is_array($parsed['scorers']) || count($parsed['scorers']) === 0) {
    sse_error('Reponse Claude invalide', [
        'raw_excerpt' => substr($accumulated_text, 0, 1500),
        'json_error' => json_last_error_msg(),
    ]);
}

// Pricing Opus 4.7
$cost = ($tokens_in / 1_000_000.0) * 15 + ($tokens_out / 1_000_000.0) * 75;

// ===== Cache en DB =====
SE_Db::execute(
    "INSERT INTO match_scorer_analysis
     (match_id, scorers_json, markdown_full, warnings_json, freshness_note,
      searches_json, raw_response, model_used, tokens_input, tokens_output,
      web_searches_count, cost_usd, duration_seconds)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
       scorers_json = VALUES(scorers_json),
       markdown_full = VALUES(markdown_full),
       warnings_json = VALUES(warnings_json),
       freshness_note = VALUES(freshness_note),
       searches_json = VALUES(searches_json),
       raw_response = VALUES(raw_response),
       tokens_input = VALUES(tokens_input),
       tokens_output = VALUES(tokens_output),
       web_searches_count = VALUES(web_searches_count),
       cost_usd = VALUES(cost_usd),
       duration_seconds = VALUES(duration_seconds),
       generated_at = NOW()",
    [
        $match_id,
        json_encode($parsed['scorers'], JSON_UNESCAPED_UNICODE),
        $parsed['markdown_full'] ?? null,
        json_encode($parsed['warnings'] ?? [], JSON_UNESCAPED_UNICODE),
        $parsed['freshness_note'] ?? null,
        json_encode($searches_done, JSON_UNESCAPED_UNICODE),
        $accumulated_text,
        'claude-opus-4-7',
        $tokens_in,
        $tokens_out,
        $web_search_count,
        round($cost, 4),
        $duration,
    ]
);

sse_send('complete', [
    'cached' => false,
    'generated_at' => date('Y-m-d H:i:s'),
    'scorers' => $parsed['scorers'],
    'warnings' => $parsed['warnings'] ?? [],
    'freshness_note' => $parsed['freshness_note'] ?? null,
    'match_summary' => $parsed['match_summary'] ?? null,
    'markdown_full' => $parsed['markdown_full'] ?? null,
    'searches' => $searches_done,
    'model_used' => 'claude-opus-4-7',
    'tokens_input' => $tokens_in,
    'tokens_output' => $tokens_out,
    'web_searches_count' => $web_search_count,
    'cost_usd' => round($cost, 4),
    'duration_seconds' => $duration,
]);
