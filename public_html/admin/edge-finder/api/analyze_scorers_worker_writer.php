<?php
/**
 * STRATEDGE - Edge Finder - WORKER ETAPE 2 (writer)
 * ================================================================================
 *
 * Etape 2/2 de l'analyse buteurs asynchrone.
 *   - Recoit le contexte match + la synthese de l'etape 1 (en base)
 *   - PAS de recherche web (le tool n'est pas dans le payload)
 *   - Redige le JSON final SNIPER : 3 buteurs avec sniper_score, radar,
 *     key_stats, verdict, confidence, probas, reasoning, warnings
 *   - Passe le job a status='done'
 *
 * Declenche automatiquement par status.php quand il voit status='researched'.
 * Tient en <120s car PAS de recherches web a faire.
 *
 *   GET ?match_id=N&worker_token=XXX
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../config-keys.php';
require_once __DIR__ . '/../lib/db.php';

ignore_user_abort(true);
@set_time_limit(300);

$match_id = (int)($_GET['match_id'] ?? 0);
$worker_token = (string)($_GET['worker_token'] ?? '');

if (!defined('SE_WORKER_TOKEN') || !hash_equals(SE_WORKER_TOKEN, $worker_token)) {
    http_response_code(403);
    exit('forbidden');
}
if ($match_id <= 0) {
    http_response_code(400);
    exit('match_id required');
}

function writer_fail(int $match_id, string $msg): void {
    try {
        SE_Db::reconnect();
        SE_Db::execute(
            "UPDATE match_scorer_analysis SET status = 'error', error_msg = ? WHERE match_id = ?",
            [mb_substr($msg, 0, 490), $match_id]
        );
    } catch (Throwable $e) { /* best effort */ }
    exit;
}

// ===== Recupere job + synthese etape 1 =====
$job = SE_Db::queryOne(
    "SELECT msa.*, m.home_name, m.away_name, m.kickoff_utc, m.lambda_home, m.lambda_away,
            m.league_name, m.league_country AS country
     FROM match_scorer_analysis msa
     JOIN pick_matches m ON m.match_id = msa.match_id
     WHERE msa.match_id = ?",
    [$match_id]
);

if (!$job) {
    writer_fail($match_id, 'Job ou match introuvable pour etape 2');
}
if ($job['status'] !== 'researched') {
    writer_fail($match_id, 'Etape 2 appelee sur un job en status ' . $job['status'] . ' (attendu: researched)');
}
if (empty($job['research_summary'])) {
    writer_fail($match_id, 'Synthese etape 1 vide - relance l\'analyse');
}

$summary = json_decode($job['research_summary'], true);
if (!is_array($summary)) {
    writer_fail($match_id, 'Synthese etape 1 non parsable');
}

// Marque 'running' (etape 2 demarre) + reset le tag writer_kicked
SE_Db::execute(
    "UPDATE match_scorer_analysis SET status = 'running', started_at = NOW(), error_msg = NULL WHERE match_id = ?",
    [$match_id]
);

// ===== Recupere cotes marche =====
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

$lambda_home = (float)($job['lambda_home'] ?? 1.4);
$lambda_away = (float)($job['lambda_away'] ?? 1.2);
$lambda_total = $lambda_home + $lambda_away;
$kickoff_paris = (new DateTime($job['kickoff_utc']))->format('d/m/Y H:i');

// ===== Charge le prompt v1.1 (rendu SNIPER) =====
$prompt_path = __DIR__ . '/../prompts/PROMPT_BUTEURS_v1_1.md';
if (!file_exists($prompt_path)) {
    writer_fail($match_id, 'Fichier prompt v1.1 introuvable');
}
$prompt_template = file_get_contents($prompt_path);

$prompt = strtr($prompt_template, [
    '{{home_name}}' => $job['home_name'],
    '{{away_name}}' => $job['away_name'],
    '{{league_name}}' => $job['league_name'] ?? 'inconnue',
    '{{country}}' => $job['country'] ?? '',
    '{{kickoff_paris}}' => $kickoff_paris,
    '{{lambda_home}}' => number_format($lambda_home, 2),
    '{{lambda_away}}' => number_format($lambda_away, 2),
    '{{lambda_total}}' => number_format($lambda_total, 2),
    '{{market_odds}}' => $market_odds,
]);

// Injection de la synthese etape 1 + instruction de NE PAS faire de recherche
$summary_pretty = json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$context_block = <<<CTX
⚠️ INSTRUCTIONS IMPORTANTES :
- N'EFFECTUE AUCUNE recherche web (le tool n'est pas disponible). Toutes les
  infos brutes te sont DEJA fournies ci-dessous.
- Utilise UNIQUEMENT les infos ci-dessous pour rediger le JSON final SNIPER.
- Si une info manque, mets "N/D" ou null - n'invente JAMAIS.

INFOS BRUTES (synthese de la phase de recherche) :
```json
{$summary_pretty}
```

Tu dois maintenant transformer ces infos brutes en JSON SNIPER final, selon
le format defini ci-dessous (sniper_score, stars, verdict, confidence, radar,
key_stats, reasoning, etc. pour TOP 3 buteurs).


CTX;

$prompt = $context_block . $prompt;

// ===== Appel Anthropic SANS web_search (etape 2 = redaction pure) =====
if (!defined('ANTHROPIC_API_KEY') || !ANTHROPIC_API_KEY) {
    writer_fail($match_id, 'ANTHROPIC_API_KEY non configure');
}

$payload = [
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 8000,
    // PAS de 'tools' : redaction pure sans recherche
    'messages' => [
        ['role' => 'user', 'content' => $prompt]
    ],
];

$start_time = microtime(true);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 115,
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
    writer_fail($match_id, "Etape 2 (writer) - Anthropic HTTP $http_code : " . mb_substr($curl_err ?: (string)$response, 0, 250));
}

$resp = json_decode($response, true);
if (!is_array($resp)) {
    writer_fail($match_id, 'Etape 2 - reponse Anthropic non parsable');
}

$accumulated_text = '';
foreach (($resp['content'] ?? []) as $block) {
    if (($block['type'] ?? '') === 'text') {
        $accumulated_text .= $block['text'] ?? '';
    }
}

$tokens_in = (int)($resp['usage']['input_tokens'] ?? 0);
$tokens_out = (int)($resp['usage']['output_tokens'] ?? 0);
$duration = (int)round(microtime(true) - $start_time);

// Parse le JSON SNIPER final
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
    writer_fail($match_id, $looks_truncated
        ? 'Etape 2 interrompue (JSON tronque) - relance l\'analyse'
        : 'Etape 2 - JSON SNIPER invalide (' . json_last_error_msg() . ')');
}

// Pricing Sonnet 4.6 : $3 input / $15 output par MTok
// Cout total = etape1 + etape2
$research_in  = (int)($job['research_tokens_input'] ?? 0);
$research_out = (int)($job['research_tokens_output'] ?? 0);
$total_in  = $research_in + $tokens_in;
$total_out = $research_out + $tokens_out;
$cost = ($total_in / 1_000_000.0) * 3 + ($total_out / 1_000_000.0) * 15;

$research_dur = (int)($job['research_duration_seconds'] ?? 0);
$total_duration = $research_dur + $duration;

// ===== Ecrit le resultat final, status 'done' =====
SE_Db::reconnect();
SE_Db::execute(
    "UPDATE match_scorer_analysis SET
       status = 'done',
       error_msg = NULL,
       scorers_json = ?,
       markdown_full = ?,
       warnings_json = ?,
       freshness_note = ?,
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
        $accumulated_text,
        'claude-sonnet-4-6',
        $total_in,
        $total_out,
        (int)count(json_decode($job['searches_json'] ?? '[]', true) ?: []),
        round($cost, 4),
        $total_duration,
        $match_id,
    ]
);

exit('writer ok');
