<?php
// ============================================================
// STRATEDGE — Génération automatique de tweets via Claude API
// + Recherche du vrai résultat du match via API sportive
// ============================================================

require_once __DIR__ . '/claude-config.php';

/**
 * Extrait les noms des 2 équipes/joueurs depuis le titre du bet.
 * Formats attendus : "Equipe1 vs Equipe2", "Equipe1 - Equipe2", etc.
 */
function extraireEquipes(string $titre): array {
    $titre = trim($titre);
    $separateurs = [' vs ', ' VS ', ' Vs ', ' - ', ' – ', ' — ', ' contre '];
    foreach ($separateurs as $sep) {
        if (stripos($titre, $sep) !== false) {
            $parts = explode($sep, $titre, 2);
            $team1 = trim($parts[0]);
            $team2 = trim($parts[1]);
            $team2 = preg_replace('/\s*[-–—:].+$/', '', $team2);
            return [$team1, $team2];
        }
    }
    return [$titre, ''];
}

/**
 * Recherche les résultats récents d'un match via TheSportsDB (gratuit).
 * Retourne un résumé texte des événements du match.
 */
function rechercherResultatMatch(string $titre, string $sport): string {
    [$team1, $team2] = extraireEquipes($titre);
    if ($team1 === '' || $team2 === '') return '';

    $resultats = [];

    // 1) Recherche par nom d'événement
    $query = urlencode(trim($team1) . '_vs_' . trim($team2));
    $url = "https://www.thesportsdb.com/api/v1/json/3/searchevents.php?e={$query}";
    $match = fetchMatchFromApi($url, $team1, $team2);

    if (!$match) {
        $query2 = urlencode(trim($team2) . '_vs_' . trim($team1));
        $url2 = "https://www.thesportsdb.com/api/v1/json/3/searchevents.php?e={$query2}";
        $match = fetchMatchFromApi($url2, $team1, $team2);
    }

    // 2) Fallback : derniers événements par équipe
    if (!$match) {
        $searchUrl = "https://www.thesportsdb.com/api/v1/json/3/searchteams.php?t=" . urlencode(trim($team1));
        $teamData = @json_decode(@file_get_contents($searchUrl), true);
        $teamId = $teamData['teams'][0]['idTeam'] ?? null;
        if ($teamId) {
            $lastUrl = "https://www.thesportsdb.com/api/v1/json/3/eventslast.php?id={$teamId}";
            $match = fetchMatchFromApi($lastUrl, $team1, $team2, 'results');
        }
    }

    if (!$match) {
        error_log('[tweet-ai] Aucun match trouvé via API pour "' . $titre . '"');
        return '';
    }

    $home  = $match['strHomeTeam'] ?? '';
    $away  = $match['strAwayTeam'] ?? '';
    $scoreH = $match['intHomeScore'] ?? '?';
    $scoreA = $match['intAwayScore'] ?? '?';
    $date   = $match['dateEvent'] ?? '';
    $comp   = $match['strLeague'] ?? '';
    $round  = $match['intRound'] ?? '';
    $venue  = $match['strVenue'] ?? '';

    $resume = "RÉSULTAT RÉEL DU MATCH :\n";
    $resume .= "{$home} {$scoreH} - {$scoreA} {$away}\n";
    if ($comp) $resume .= "Compétition : {$comp}\n";
    if ($date) $resume .= "Date : {$date}\n";
    if ($venue) $resume .= "Stade : {$venue}\n";

    // Statistiques et événements
    $timeline = $match['strTimeline'] ?? '';
    if ($timeline) {
        $resume .= "Événements du match : {$timeline}\n";
    }
    $stats = $match['strResult'] ?? $match['strDescriptionEN'] ?? '';
    if ($stats) {
        $resume .= "Résumé : {$stats}\n";
    }

    // Cartons, buts (champs spécifiques TheSportsDB)
    $homeGoals = $match['strHomeGoalDetails'] ?? '';
    $awayGoals = $match['strAwayGoalDetails'] ?? '';
    $homeCards = $match['strHomeYellowCards'] ?? '';
    $awayCards = $match['strAwayYellowCards'] ?? '';
    $homeRed   = $match['strHomeRedCards'] ?? '';
    $awayRed   = $match['strAwayRedCards'] ?? '';

    if ($homeGoals || $awayGoals) {
        $resume .= "Buteurs : ";
        if ($homeGoals) $resume .= "{$home}: {$homeGoals} ";
        if ($awayGoals) $resume .= "{$away}: {$awayGoals}";
        $resume .= "\n";
    }
    if ($homeCards || $awayCards) {
        $resume .= "Cartons jaunes : ";
        if ($homeCards) $resume .= "{$home}: {$homeCards} ";
        if ($awayCards) $resume .= "{$away}: {$awayCards}";
        $resume .= "\n";
    }
    if ($homeRed || $awayRed) {
        $resume .= "Cartons rouges : ";
        if ($homeRed) $resume .= "{$home}: {$homeRed} ";
        if ($awayRed) $resume .= "{$away}: {$awayRed}";
        $resume .= "\n";
    }

    error_log('[tweet-ai] Match trouvé : ' . $home . ' ' . $scoreH . '-' . $scoreA . ' ' . $away);
    return $resume;
}

/**
 * Parse la réponse API TheSportsDB et trouve le match le plus récent.
 */
function fetchMatchFromApi(string $url, string $team1, string $team2, string $key = 'event'): ?array {
    $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
    $json = @file_get_contents($url, false, $ctx);
    if (!$json) return null;

    $data = @json_decode($json, true);
    if (!$data) return null;

    $events = $data[$key] ?? $data['events'] ?? $data['results'] ?? null;
    if (!is_array($events) || empty($events)) return null;

    // Chercher le match le plus récent correspondant aux deux équipes
    $t1 = mb_strtolower(trim($team1));
    $t2 = mb_strtolower(trim($team2));

    $bestMatch = null;
    $bestDate  = '';
    foreach ($events as $evt) {
        $h = mb_strtolower($evt['strHomeTeam'] ?? '');
        $a = mb_strtolower($evt['strAwayTeam'] ?? '');
        $evtName = mb_strtolower($evt['strEvent'] ?? '');

        $match1 = (str_contains($h, $t1) || str_contains($t1, $h) || str_contains($evtName, $t1));
        $match2 = (str_contains($a, $t2) || str_contains($t2, $a) || str_contains($evtName, $t2));
        $matchReverse1 = (str_contains($h, $t2) || str_contains($t2, $h));
        $matchReverse2 = (str_contains($a, $t1) || str_contains($t1, $a));

        if (($match1 && $match2) || ($matchReverse1 && $matchReverse2)) {
            $d = $evt['dateEvent'] ?? '';
            if ($d > $bestDate) {
                $bestDate = $d;
                $bestMatch = $evt;
            }
        }
    }

    // Si pas de match exact, prendre le premier résultat récent
    if (!$bestMatch && !empty($events[0]['intHomeScore'])) {
        $bestMatch = $events[0];
    }

    return $bestMatch;
}


function genererTweetExplication(array $bet, string $resultat): string {
    $titre      = $bet['titre'] ?? '';
    $description = $bet['description'] ?? '';
    $analyse    = strip_tags($bet['analyse_html'] ?? '');
    $cote       = $bet['cote'] ?? '';
    $sport      = $bet['sport'] ?? 'football';
    $categorie  = $bet['categorie'] ?? 'multi';
    $type       = $bet['type'] ?? 'safe';

    $resultatLabel = ['gagne' => 'GAGNÉ', 'perdu' => 'PERDU', 'annule' => 'ANNULÉ'][$resultat] ?? $resultat;

    // Rechercher le vrai résultat du match via API sportive
    $matchData = rechercherResultatMatch($titre, $sport);
    $matchContext = '';
    if ($matchData !== '') {
        $matchContext = "\n\n⚠️ DONNÉES RÉELLES DU MATCH (utilise UNIQUEMENT ces données, ne rien inventer) :\n{$matchData}";
    }

    $prompt = <<<PROMPT
Tu es le community manager de StratEdge Pronos.

Le bet suivant vient d'être marqué comme {$resultatLabel}.

Infos du bet :
- Titre : {$titre}
- Sport : {$sport}
- Type de bet : {$type}
- Cote : {$cote}
- Description du pari : {$description}
- Analyse pré-match : {$analyse}{$matchContext}

⚠️ RÈGLES ABSOLUES :
1. Tu dois parler EXACTEMENT de CE bet et CE match précis (pas un autre match)
2. Le titre du bet contient le nom des équipes/joueurs ET le type de pari (ex: "+0.5 carton Vancouver")
3. Tu dois expliquer concrètement COMMENT le bet s'est déroulé en rapport avec le pari
4. Si des données réelles du match sont fournies (score, buteurs, cartons), utilise-les OBLIGATOIREMENT
5. Si aucune donnée réelle n'est fournie, reste vague sur les détails ("le match s'est déroulé comme prévu" / "le scénario ne s'est pas réalisé") mais mentionne TOUJOURS le type de pari exact
6. NE JAMAIS inventer de score, de joueur ou d'événement que tu ne connais pas
7. Mentionne le type de pari (carton, but, victoire, handicap...) dans l'explication

Format : 1-2 phrases courtes, MAXIMUM 100 caractères au total.
Ton décontracté mais pro, tutoiement.
PAS d'emoji, PAS de hashtag, PAS de lien, PAS de préambule.
En français.

Réponds UNIQUEMENT avec les 1-2 phrases (100 caractères max).
PROMPT;

    try {
        $payload = json_encode([
            'model'      => 'claude-sonnet-4-20250514',
            'max_tokens' => 250,
            'messages'   => [
                ['role' => 'user', 'content' => $prompt]
            ]
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . CLAUDE_API_KEY,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT        => 20,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300 && $response) {
            $data = json_decode($response, true);
            $text = $data['content'][0]['text'] ?? '';
            $text = trim($text);
            if ($text !== '') {
                // Tronquer à 120 chars max (sécurité pour rester dans les 280 chars Twitter)
                if (mb_strlen($text) > 120) {
                    $text = mb_substr($text, 0, 117) . '...';
                }
                error_log('[tweet-ai] Explication générée pour bet "' . $titre . '" (' . $resultat . ') — match data: ' . ($matchData !== '' ? 'OUI' : 'NON'));
                return $text;
            }
        }

        error_log('[tweet-ai] Échec API Claude (HTTP ' . $httpCode . ') pour bet "' . $titre . '"');
    } catch (\Throwable $e) {
        error_log('[tweet-ai] Erreur : ' . $e->getMessage());
    }

    return '';
}

// ============================================================
// GÉNÉRATION TWEET ALGO-FRIENDLY pour bet MULTI SAFE
// ============================================================
// Tweet auto-posté quand admin poste un bet Multi Safe (superadmin).
// Format optimisé pour l'algo X 2026:
//   - PAS DE LIEN externe dans le tweet principal (tueur de portée)
//   - PAS DE "X likes pour débloquer" (pattern engagement-farming détecté)
//   - QUESTION ouverte qui invite aux replies (replies = 13.5x les likes)
//   - 2-3 hashtags max
// La reply avec le lien + SMS est postée MANUELLEMENT par l'admin après.
function genererTweetVerrouilleAlgo(array $bet): string {
    $titre = trim((string)($bet['titre'] ?? ''));
    $sport = trim((string)($bet['sport'] ?? 'football'));
    $cote  = trim((string)($bet['cote'] ?? ''));

    if ($titre === '') return '';
    if (!defined('CLAUDE_API_KEY') || CLAUDE_API_KEY === '') return '';

    // Mapping sport → emoji + hashtags (selon prefs user, ordre exact respecté)
    $sportConfig = [
        'football'   => ['emoji' => '⚽', 'tags' => '#TeamParieur #Foot'],
        'foot'       => ['emoji' => '⚽', 'tags' => '#TeamParieur #Foot'],
        'soccer'     => ['emoji' => '⚽', 'tags' => '#TeamParieur #Foot'],
        'basket'     => ['emoji' => '🏀', 'tags' => '#TeamParieur #NBA'],
        'basketball' => ['emoji' => '🏀', 'tags' => '#TeamParieur #NBA'],
        'tennis'     => ['emoji' => '🎾', 'tags' => '#Tennis #teamparieur'],
        'hockey'     => ['emoji' => '🏒', 'tags' => '#NHL #TeamParieur'],
        'baseball'   => ['emoji' => '⚾', 'tags' => '#TeamParieur #MLB'],
    ];
    $sk = strtolower($sport);
    $sportEmoji = $sportConfig[$sk]['emoji'] ?? '🎯';
    $sportTags  = $sportConfig[$sk]['tags']  ?? '#TeamParieur #Foot';

    $prompt = <<<PROMPT
Tu es community manager de StratEdge Pronos (site de pronostics sportifs).

MISSION: générer UN tweet pour annoncer un nouveau pronostic.
Le tweet sera posté sur X (Twitter). Il doit MAXIMISER LA PORTÉE selon l'algo X 2026.

Infos du bet:
- Match: {$titre}
- Sport: {$sport}
- Cote retenue: {$cote}
- Type: Multi Safe (analyse complète sur le site, pick verrouillé sur X)

⚠️ RÈGLES ABSOLUES (algo X 2026):

1. AUCUN LIEN externe (pas de "stratedgepronos.fr" dans le tweet — l'algo punit lourdement les liens, jusqu'à -90% de portée)
2. AUCUN "X likes pour débloquer", "RT pour voir", "follow pour recevoir" → ces patterns sont détectés comme engagement-farming et supprimés activement par l'algo
3. POSE UNE QUESTION OUVERTE en fin de tweet qui invite aux replies — c'est le levier le plus puissant (replies = 13.5x le poids des likes dans le scoring)
4. TON conversationnel, naturel, expert mais accessible — pas de marketing forcé
5. Mentionne le match avec emoji sport ({$sportEmoji}) + heure si présente dans le titre
6. Donne un mini-teaser sur TON ANGLE d'analyse SANS révéler le pick (genre une stat intéressante, un pattern observé, un facteur clé)
7. Termine par EXACTEMENT ces 2 hashtags, dans cet ordre précis : {$sportTags}

⚠️ INTERDITS:
- 👇 agressif type "👇 dis moi en commentaire"
- "Mon pick verrouillé" formulé comme un cliffhanger commercial
- Émojis cliquetis (🚨🔥💯💎) en chaine
- Promesses de gains ("100% sûr", "banco", "value énorme")
- Ton vendeur ("ne ratez pas", "à ne pas manquer")

⚠️ LONGUEUR: 220 caractères MAX au total (avec hashtags).

⚠️ STRUCTURE QUI MARCHE:
{$sportEmoji} [Match] [heure si dispo]

[1-2 lignes: ton angle d'analyse, fait intéressant, pattern, contexte]

[1 question ouverte au lecteur, genre "Vous voyez quoi sur ce match?" "Pari ouvert ou fermé pour vous?" "Lequel des 2 a l'avantage selon toi?"]

{$sportTags}

⚠️ EXEMPLES DE BON STYLE (imite le ton, pas le contenu spécifique):

Exemple 1:
"⚽ Real Madrid vs City · 21h00

Au Bernabeu en quart C1, Madrid n'a pas perdu depuis 2014. Mais City arrive en pleine forme post-trêve.

Lequel des 2 a l'avantage selon toi?

#TeamParieur #Foot"

Exemple 2:
"⚽ Lyon vs Marseille · 20h45

Le Vélodrome reste un casse-tête pour les visiteurs (1 défaite OM en L1 cette saison à domicile). Mais Lyon vient avec ses 5 victoires consécutives.

Vous voyez ça comment ce derby?

#TeamParieur #Foot"

⚠️ N'invente AUCUNE stat précise si tu n'es pas sûr. Reste général sur ton angle (rythme, contexte, dynamique des équipes).

Réponds UNIQUEMENT avec le tweet, rien d'autre. Pas de préambule, pas de guillemets, pas d'explication.
PROMPT;

    try {
        $payload = json_encode([
            'model'      => 'claude-sonnet-4-20250514',
            'max_tokens' => 400,
            'messages'   => [
                ['role' => 'user', 'content' => $prompt]
            ]
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . CLAUDE_API_KEY,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT        => 25,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300 && $response) {
            $data = json_decode($response, true);
            $text = $data['content'][0]['text'] ?? '';
            $text = trim($text);
            // Sécurité: enlever guillemets si Claude les a mis malgré l'instruction
            $text = trim($text, " \t\n\r\0\x0B\"'");
            if ($text !== '' && mb_strlen($text) <= 280) {
                error_log('[tweet-ai] Tweet algo Multi Safe généré pour "' . $titre . '" (' . mb_strlen($text) . ' chars)');
                return $text;
            }
            error_log('[tweet-ai] Tweet algo Multi Safe rejeté (vide ou >280 chars) pour "' . $titre . '"');
        } else {
            error_log('[tweet-ai] Échec API Claude tweet algo (HTTP ' . $httpCode . ') pour "' . $titre . '"');
        }
    } catch (\Throwable $e) {
        error_log('[tweet-ai] Erreur tweet algo: ' . $e->getMessage());
    }

    return '';
}

// Reply suggérée à poster MANUELLEMENT sur X par l'admin après le tweet principal.
// Le lien externe et l'instruction SMS sont mis ICI pour ne pas tuer la portée du tweet principal.
function suggestionReplyVerrouille(): string {
    $variants = [
        "🎯 Pour ceux qui veulent l'analyse complète + ma confiance + la cote :\n\nstratedgepronos.fr\n\nOu plus rapide : SMS STRATEDGE au 81004 (4,50€) après inscription sur le site.",
        "📊 L'analyse Dixon-Coles + xG + le pick complet sont sur :\n\nstratedgepronos.fr\n\nPour les pressés : SMS STRATEDGE au 81004 (4,50€) après inscription.",
        "🔓 Le pick + l'analyse complète sont disponibles sur :\n\nstratedgepronos.fr\n\nAccès direct via SMS STRATEDGE au 81004 (4,50€) après inscription sur le site.",
    ];
    return $variants[array_rand($variants)];
}
