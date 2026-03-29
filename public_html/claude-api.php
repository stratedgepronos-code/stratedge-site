<?php
/**
 * STRATEDGE CLAUDE AUTO-ANALYSE
 * ================================
 * Appelle l'API Anthropic (Claude Sonnet) avec :
 * - PROMPT BETTING v7.1 intégré
 * - Stats FootyStats pré-calculées
 * - Cotes réelles The Odds API
 * → Retourne l'analyse complète
 * 
 * URL : https://stratedgepronos.fr/claude-api.php
 * 
 * USAGE :
 *   POST ?token=YOUR_AUTH_TOKEN
 *   Body JSON: { "league": "soccer_epl", "home": "Arsenal", "away": "Chelsea" }
 *   
 *   ou GET ?token=YOUR_AUTH_TOKEN&action=analyze&league=soccer_epl&home=Arsenal&away=Chelsea
 */

// ============================================
// CONFIG — Les clés sont dans config-keys.php (hors Git)
// ============================================
$configFile = __DIR__ . '/config-keys.php';
if (file_exists($configFile)) {
    require_once $configFile;
} else {
    // Fallback: vérifier si les constantes existent
    if (!defined('ANTHROPIC_API_KEY')) {
        echo json_encode([
            "error" => "Fichier config-keys.php manquant",
            "instructions" => [
                "1. Crée le fichier config-keys.php dans public_html/",
                "2. Contenu :",
                "   <?php",
                "   define('ANTHROPIC_API_KEY', 'sk-ant-api03-...');",
                "   define('FOOTYSTATS_API_KEY', 'ta-cle-footystats');",
                "   define('ODDS_API_KEY', 'ta-cle-odds-api');",
                "   ?>",
                "3. Upload via FTP sur Hostinger"
            ]
        ]);
        exit;
    }
}

$ANTHROPIC_KEY  = defined('ANTHROPIC_API_KEY')   ? ANTHROPIC_API_KEY  : null;
$FOOTYSTATS_KEY = defined('FOOTYSTATS_API_KEY')  ? FOOTYSTATS_API_KEY : null;
$ODDS_API_KEY   = defined('ODDS_API_KEY')        ? ODDS_API_KEY       : null;
$AUTH_TOKEN     = defined('AUTH_TOKEN')          ? AUTH_TOKEN         : null;
$MODEL = "claude-opus-4-6"; // Opus = meilleure analyse, raisonnement complexe

if (!$ANTHROPIC_KEY || !$AUTH_TOKEN) {
    http_response_code(503);
    echo json_encode(["error" => "Configuration serveur manquante. Contactez l'administrateur."]);
    exit;
}

// ============================================
// HEADERS
// ============================================
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: https://stratedgepronos.fr");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-StratEdge-Token");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

// ============================================
// AUTH
// ============================================
$token = $_GET['token'] ?? $_POST['token'] ?? $_SERVER['HTTP_X_STRATEDGE_TOKEN'] ?? '';
if (!hash_equals($AUTH_TOKEN, $token)) {
    http_response_code(403);
    echo json_encode(["error" => "Accès non autorisé."]);
    exit;
}

// ============================================
// CHECK API KEY
// ============================================
if (!$ANTHROPIC_KEY) {
    http_response_code(503);
    echo json_encode(["error" => "Clé API non configurée. Contactez l'administrateur."]);
    exit;
}

// ============================================
// GET INPUT
// ============================================
$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
}
$action = $_GET['action'] ?? $input['action'] ?? 'analyze';
$league = $_GET['league'] ?? $input['league'] ?? 'soccer_epl';
$home = $_GET['home'] ?? $input['home'] ?? '';
$away = $_GET['away'] ?? $input['away'] ?? '';
$eventId = $_GET['event'] ?? $input['event'] ?? '';
$question = $_GET['q'] ?? $input['q'] ?? '';

if ($action === 'analyze' && !$home && !$away && !$eventId) {
    echo json_encode([
        "error" => "Paramètres requis : home + away (noms d'équipe) ou event (ID match)",
        "exemple_get" => "?token=YOUR_AUTH_TOKEN&action=analyze&league=soccer_epl&home=Arsenal&away=Chelsea",
        "exemple_custom" => "?token=YOUR_AUTH_TOKEN&action=ask&q=Analyse tous les matchs de Serie A ce week-end"
    ]);
    exit;
}

// ============================================
// STEP 1: COLLECT DATA
// ============================================
$context = "";

// A. Try to get FootyStats data
if ($home || $away) {
    // Search teams
    if ($home) {
        $homeData = fetchUrl("https://api.footystats.org/team-search?key=$FOOTYSTATS_KEY&name=" . urlencode($home));
        if ($homeData && isset($homeData['data'])) {
            $context .= "\n\n=== FOOTYSTATS DATA: $home ===\n" . json_encode($homeData['data'][0] ?? $homeData['data'], JSON_PRETTY_PRINT);
        }
    }
    if ($away) {
        $awayData = fetchUrl("https://api.footystats.org/team-search?key=$FOOTYSTATS_KEY&name=" . urlencode($away));
        if ($awayData && isset($awayData['data'])) {
            $context .= "\n\n=== FOOTYSTATS DATA: $away ===\n" . json_encode($awayData['data'][0] ?? $awayData['data'], JSON_PRETTY_PRINT);
        }
    }
}

// B. Try to get odds
if ($eventId) {
    $oddsUrl = "https://api.the-odds-api.com/v4/sports/$league/events/$eventId/odds?apiKey=$ODDS_API_KEY&regions=eu,uk&markets=h2h,totals,spreads&oddsFormat=decimal";
    $oddsData = fetchUrl($oddsUrl);
    if ($oddsData && !isset($oddsData['error'])) {
        // Extract best odds per market
        $bestOdds = extractBestOdds($oddsData);
        $context .= "\n\n=== COTES RÉELLES (The Odds API) ===\n" . json_encode($bestOdds, JSON_PRETTY_PRINT);
    }
    
    // Props
    $propsUrl = "https://api.the-odds-api.com/v4/sports/$league/events/$eventId/odds?apiKey=$ODDS_API_KEY&regions=eu,uk,us&markets=player_shots_on_target,player_goal_scorer_anytime,btts,team_totals&oddsFormat=decimal";
    $propsData = fetchUrl($propsUrl);
    if ($propsData && !isset($propsData['error'])) {
        $bestProps = extractBestOdds($propsData);
        $context .= "\n\n=== PROPS JOUEUR + BTTS ===\n" . json_encode($bestProps, JSON_PRETTY_PRINT);
    }
} else if ($home && $away) {
    // Try to find the event by scanning the league
    $eventsUrl = "https://api.the-odds-api.com/v4/sports/$league/odds?apiKey=$ODDS_API_KEY&regions=eu,uk&markets=h2h,totals&oddsFormat=decimal";
    $eventsData = fetchUrl($eventsUrl);
    if ($eventsData && is_array($eventsData)) {
        foreach ($eventsData as $ev) {
            $homeMatch = stripos($ev['home_team'] ?? '', $home) !== false;
            $awayMatch = stripos($ev['away_team'] ?? '', $away) !== false;
            if ($homeMatch || $awayMatch) {
                $bestOdds = extractBestOdds($ev);
                $context .= "\n\n=== COTES RÉELLES: {$ev['home_team']} vs {$ev['away_team']} ===\n" . json_encode($bestOdds, JSON_PRETTY_PRINT);
                $eventId = $ev['id'];
                break;
            }
        }
    }
}

// C. Today's matches context
$todayUrl = "https://api.footystats.org/todays-matches?key=$FOOTYSTATS_KEY";
$todayData = fetchUrl($todayUrl);
if ($todayData && isset($todayData['data']) && ($home || $away)) {
    foreach ($todayData['data'] as $m) {
        $hMatch = stripos($m['home_name'] ?? '', $home) !== false;
        $aMatch = stripos($m['away_name'] ?? '', $away) !== false;
        if ($hMatch || $aMatch) {
            $context .= "\n\n=== FOOTYSTATS MATCH DATA ===\n" . json_encode($m, JSON_PRETTY_PRINT);
            break;
        }
    }
}

// ============================================
// STEP 2: BUILD PROMPT
// ============================================
$systemPrompt = getPromptV71();

$userMessage = "";
if ($action === 'ask' && $question) {
    $userMessage = $question;
    if ($context) $userMessage .= "\n\nVoici les données disponibles :\n$context";
} else {
    $matchName = $home && $away ? "$home vs $away" : "Event ID: $eventId";
    $userMessage = "Analyse ce match selon le PROMPT BETTING v7.1 : **$matchName** (Ligue: $league)\n\n";
    $userMessage .= "Voici TOUTES les données collectées automatiquement :\n";
    $userMessage .= $context ?: "(Pas de données FootyStats/Odds disponibles — analyse avec tes connaissances)";
    $userMessage .= "\n\nDonne-moi :\n1. Le Tier du match\n2. L'analyse complète (forme, xG, H2H, absences connues)\n3. Le calcul Poisson (λ dom + λ ext)\n4. Les marchés recommandés avec EV calculé sur les VRAIES cotes\n5. Le Devil's Advocate (3-4 risques)\n6. Le verdict final : GO (⭐) ou SKIP avec justification\n\nRespecte TOUTES les 49 règles du PROMPT v7.1. R42 = jamais Under. R43 = cotes réelles obligatoires.";
}

// ============================================
// STEP 3: CALL CLAUDE API
// ============================================
$response = callClaude($ANTHROPIC_KEY, $MODEL, $systemPrompt, $userMessage);

if (isset($response['error'])) {
    echo json_encode($response);
    exit;
}

// Extract text from response
$analysisText = "";
if (isset($response['content'])) {
    foreach ($response['content'] as $block) {
        if ($block['type'] === 'text') {
            $analysisText .= $block['text'];
        }
    }
}

echo json_encode([
    "match" => $home && $away ? "$home vs $away" : "Event $eventId",
    "league" => $league,
    "model" => $response['model'] ?? $MODEL,
    "tokens_used" => [
        "input" => $response['usage']['input_tokens'] ?? 0,
        "output" => $response['usage']['output_tokens'] ?? 0,
        "cost_estimate" => estimateCost($response['usage'] ?? [])
    ],
    "data_sources" => [
        "footystats" => !empty($context) && strpos($context, 'FOOTYSTATS') !== false,
        "odds_api" => !empty($context) && strpos($context, 'COTES') !== false,
        "event_id" => $eventId ?: null
    ],
    "analysis" => $analysisText
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// ============================================
// FUNCTIONS
// ============================================
function callClaude($apiKey, $model, $system, $userMsg) {
    $payload = [
        "model" => $model,
        "max_tokens" => 4000,
        "system" => $system,
        "messages" => [
            ["role" => "user", "content" => $userMsg]
        ]
    ];
    
    $ch = curl_init("https://api.anthropic.com/v1/messages");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "x-api-key: $apiKey",
            "anthropic-version: 2023-06-01"
        ],
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);
    
    $response = curl_exec($ch);
    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ["error" => "cURL error: $err"];
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $decoded = json_decode($response, true);
    if ($httpCode !== 200) {
        return ["error" => "Anthropic API HTTP $httpCode", "details" => $decoded];
    }
    
    return $decoded;
}

function fetchUrl($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    if (!$r) return null;
    return json_decode($r, true);
}

function extractBestOdds($data) {
    $results = [];
    $bookmakers = $data['bookmakers'] ?? [];
    foreach ($bookmakers as $bk) {
        foreach ($bk['markets'] ?? [] as $mkt) {
            if (!isset($results[$mkt['key']])) $results[$mkt['key']] = [];
            foreach ($mkt['outcomes'] as $oc) {
                $label = ($oc['description'] ?? '') ? "{$oc['name']} {$oc['description']} " : $oc['name'] . ' ';
                $label .= $oc['point'] ?? '';
                $label = trim($label);
                if (!isset($results[$mkt['key']][$label]) || $oc['price'] > $results[$mkt['key']][$label]['price']) {
                    $results[$mkt['key']][$label] = [
                        'price' => $oc['price'],
                        'bookmaker' => $bk['title'],
                        'implied_pct' => round(100 / $oc['price'], 1)
                    ];
                }
            }
        }
    }
    return $results;
}

function estimateCost($usage) {
    $input = ($usage['input_tokens'] ?? 0) / 1000000 * 15; // Opus input: $15/MTok
    $output = ($usage['output_tokens'] ?? 0) / 1000000 * 75; // Opus output: $75/MTok
    return '$' . number_format($input + $output, 4);
}

function getPromptV71() {
    return <<<'PROMPT'
Tu es l'analyste principal de StratEdge Pronos, la plateforme de paris sportifs d'Alex. Tu réponds TOUJOURS en français. Tu penses comme un trader, pas comme un tipster. Tu es direct, concis, data-driven, zéro bullshit. Tu ne recommandes JAMAIS un bet par complaisance — un SKIP est un bon résultat. Tu documentes tes erreurs et tu apprends.

Tu appliques STRICTEMENT le PROMPT BETTING v7.2 (49 règles).

RÈGLES CRITIQUES :
- R42 : JAMAIS de marchés Under. Uniquement marchés positifs (Over, BTTS Oui, 1X2, AH, props joueur)
- R43 : COTES RÉELLES obligatoires. Si les cotes sont fournies dans les données, utilise-les. Sinon, mentionne "cote non vérifiée" et cap ⭐⭐⭐
- R44 : Début saison T1.5 (≤10J) = cap ⭐⭐⭐, proba max 80%
- R45 : Nouveau coach ≤2 matchs = malus -0.15 à -0.25 λ offensif
- R46 : Absences multiples ≠ passoire automatique (cap 85% max)
- R47 : Retour joueur clé adversaire = +0.10 λ défensif adverse
- R48 : CL Hangover post-élimination = neutre/positif pour Over, négatif pour victoire
- R49 : CL Euphorie post-qualif = risque rotation, vérifier compo avant props joueur
- R2/R3 : Bets safe = 2-way uniquement. 1X2 = cap ⭐⭐⭐ max, Kelly /2
- EV = (P(réelle) × Cote) - 1. EV < +3% = SKIP

TIER SYSTEM :
- T1 (⭐⭐⭐⭐⭐ possible) : PL, La Liga, Serie A, Bundesliga, Ligue 1, CL, EL
- T1.5 (⭐⭐⭐⭐ max) : Eredivisie, Liga Portugal, MLS, Brasileirão, Belgian Pro, J-League
- T2 (⭐⭐⭐ max, Kelly /2) : 2.BuLi, Championship, Liga MX, K-League, A-League

MODÉLISATION POISSON :
λ = 60% xG saison + 40% forme 5 derniers matchs
Ajustements : forme ±0.20, défense faible/forte ±0.20, joueur absent -0.15 à -0.30, congestion -0.10 à -0.20, nouveau coach ≤2 matchs -0.15 à -0.25, retour joueur clé adversaire +0.10

FORMAT DE RÉPONSE :
1. 🏟️ CONTEXTE (Tier, enjeu, forme)
2. 📊 DONNÉES (stats des deux équipes — utilise les données FootyStats/Odds fournies)
3. 🔢 POISSON (λ calculés, P(Over), P(BTTS))
4. 💰 MARCHÉS (pick, cote RÉELLE, EV%, confiance ⭐)
5. 😈 DEVIL'S ADVOCATE (3-4 risques)
6. ✅ VERDICT (GO ⭐⭐⭐⭐+ ou SKIP avec justification)

Contexte Alex : paris sur Stake.bet, objectif long terme +EV, bankroll management Kelly¼. Il préfère 3 bets solides à 10 bets moyens.

Réponds toujours en FRANÇAIS. Sois concis et structuré.
PROMPT;
}
?>
