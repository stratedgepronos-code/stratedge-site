<?php
/**
 * STRATEDGE - Edge Finder - WORKER ETAPE 1 (research)
 * ================================================================================
 *
 * Etape 1/2 de l'analyse buteurs asynchrone.
 *   - Recoit le contexte match
 *   - Fait 4 recherches web ciblees (compos/blessures + stats 2 buteurs cles)
 *   - Renvoie une SYNTHESE STRUCTUREE (mini-JSON brut) avec :
 *       compos, blessures, buteurs candidats avec stats brutes, notes contexte
 *   - Stocke la synthese en base, passe le job a status='researched'
 *
 * Le polling (status.php) detecte ce statut et declenche le worker etape 2.
 *
 * Appel interne uniquement (token partage SE_WORKER_TOKEN).
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

function research_fail(int $match_id, string $msg): void {
    try {
        SE_Db::reconnect();
        SE_Db::execute(
            "UPDATE match_scorer_analysis SET status = 'error', error_msg = ? WHERE match_id = ?",
            [mb_substr($msg, 0, 490), $match_id]
        );
    } catch (Throwable $e) { /* best effort */ }
    exit;
}

// Marque 'running' (etape 1 demarre)
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
    research_fail($match_id, 'Match introuvable dans pick_matches');
}

$lambda_home = (float)($match['lambda_home'] ?? 1.4);
$lambda_away = (float)($match['lambda_away'] ?? 1.2);
$kickoff_paris = (new DateTime($match['kickoff_utc']))->format('d/m/Y H:i');

// ===== Construit le prompt etape 1 (recherches + synthese structuree) =====
$research_prompt = <<<PROMPT
Tu es un scout football. On t'a confie l'analyse buteurs du match :

MATCH : {$match['home_name']} (domicile) vs {$match['away_name']} (exterieur)
COMPETITION : {$match['league_name']} ({$match['country']})
COUP D'ENVOI : {$kickoff_paris} (heure de Paris)
LAMBDA POISSON (buts attendus) : home={$lambda_home}, away={$lambda_away}

TA MISSION (etape 1/2) :
Tu vas faire MAXIMUM 4 recherches web ciblees pour collecter les infos brutes,
puis tu renvoies une SYNTHESE STRUCTUREE en JSON.

BUDGET RECHERCHE (strict) :
  1. Compos probables + blessures/suspensions des 2 equipes
  2. Stats du buteur le plus probable de {$match['home_name']}
  3. Stats du buteur le plus probable de {$match['away_name']}
  4. Recherche libre selon le besoin (3eme buteur potentiel, contexte particulier)

Ne gaspille AUCUNE recherche. Apres 4 recherches, tu DOIS rediger la synthese.

FORMAT DE SORTIE (JSON STRICT, rien autour) :

```json
{
  "compos": {
    "home": {
      "lineup_status": "probable" | "confirmed" | "unknown",
      "formation": "4-3-3" | "..." | null,
      "key_starters": ["nom1", "nom2", "..."],
      "absences": [{"name": "...", "reason": "blessure|suspension|covid|autre", "impact": "majeur|moyen|mineur"}]
    },
    "away": { ... meme structure ... }
  },
  "buteurs_candidats": [
    {
      "name": "Nom complet du joueur",
      "team": "home" | "away",
      "team_label": "Nom de l'equipe",
      "position": "Attaquant axial" | "Ailier" | "Milieu offensif" | "...",
      "is_starter_probable": true | false,
      "is_injured_or_doubtful": false | true,
      "is_penalty_taker": true | false,
      "is_freekick_taker": true | false,
      "stats_brutes": {
        "goals_recent": 6,
        "goals_per_match": 0.45,
        "xg_recent": "N/D" | 4.2,
        "shots_per90": "N/D" | 3.1,
        "sot_per90": "N/D" | 1.2,
        "minutes_recent": "regulier" | "intermittent" | "remplacant",
        "form_last5": "1G0A 2G1A 0G0A 1G0A 2G0A" | "N/D"
      },
      "role_offensif": "principal" | "secondaire" | "support",
      "notes": "Texte libre sur ce qu'il faut savoir (cf source, fiabilite, contexte)"
    }
    // 3 a 5 candidats max
  ],
  "context_notes": "Texte libre sur le contexte global du match : enjeu, dynamique, infos importantes non capturees ci-dessus",
  "freshness_warning": null | "Texte si les infos sont vieilles/peu fiables",
  "sources_used": ["source1", "source2", "..."]
}
```

REGLES :
- Si une stat n'est pas trouvee : mets "N/D" (string) pour les nombres, ou null pour les autres champs
- Reste FACTUEL : pas de sniper_score ni de proba ni de verdict ici (c'est l'etape 2 qui fait ca)
- buteurs_candidats : liste 3 a 5 joueurs MAX (1-3 par equipe selon ce qu'ils valent)
- Si un joueur est blesse/suspendu : is_injured_or_doubtful=true, et explique dans "notes"
- Ne mentionne PAS de joueurs dont tu n'as pas trouve d'infos solides

Tu peux maintenant lancer tes recherches.
PROMPT;

// ===== Appel Anthropic =====
if (!defined('ANTHROPIC_API_KEY') || !ANTHROPIC_API_KEY) {
    research_fail($match_id, 'ANTHROPIC_API_KEY non configure');
}

$payload = [
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 4000,
    'tools' => [
        ['type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 4]
    ],
    'messages' => [
        ['role' => 'user', 'content' => $research_prompt]
    ],
];

$start_time = microtime(true);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 115,   // strict : on coupe AVANT la limite serveur de 122s
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
    research_fail($match_id, "Etape 1 (research) - Anthropic HTTP $http_code : " . mb_substr($curl_err ?: (string)$response, 0, 250));
}

$resp = json_decode($response, true);
if (!is_array($resp)) {
    research_fail($match_id, 'Etape 1 - reponse Anthropic non parsable');
}

// Extrait texte + comptage recherches
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
$duration = (int)round(microtime(true) - $start_time);

// ===== Parse le mini-JSON de synthese =====
$json_str = trim($accumulated_text);
if (preg_match('/```(?:json)?\s*(\{.+\})\s*```/s', $json_str, $mm)) {
    $json_str = $mm[1];
}
$first_brace = strpos($json_str, '{');
$last_brace = strrpos($json_str, '}');
if ($first_brace !== false && $last_brace !== false && $last_brace > $first_brace) {
    $json_str = substr($json_str, $first_brace, $last_brace - $first_brace + 1);
}

$summary = json_decode($json_str, true);
if (!$summary || !isset($summary['buteurs_candidats']) || !is_array($summary['buteurs_candidats']) || count($summary['buteurs_candidats']) === 0) {
    $looks_truncated = (strlen($accumulated_text) > 200)
        && (substr_count($json_str, '{') > substr_count($json_str, '}'));
    research_fail($match_id, $looks_truncated
        ? 'Etape 1 interrompue (synthese tronquee) - relance l\'analyse'
        : 'Etape 1 - synthese invalide (' . json_last_error_msg() . ')');
}

// ===== Ecrit la synthese, passe a 'researched' =====
SE_Db::reconnect();
SE_Db::execute(
    "UPDATE match_scorer_analysis SET
       status = 'researched',
       started_at = NOW(),
       research_summary = ?,
       searches_json = ?,
       research_tokens_input = ?,
       research_tokens_output = ?,
       research_duration_seconds = ?
     WHERE match_id = ?",
    [
        json_encode($summary, JSON_UNESCAPED_UNICODE),
        json_encode($searches_done, JSON_UNESCAPED_UNICODE),
        $tokens_in,
        $tokens_out,
        $duration,
        $match_id,
    ]
);

exit('research ok');
