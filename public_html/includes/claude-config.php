<?php
// ============================================================
// STRATEDGE — claude-config.php V18 (éditorial 1080x1080)
// V18 : refonte cards éditorial — nouveaux champs (n_edition, ghost,
//       kicker, pick_main, pick_accent, quote_main, quote_accent),
//       règle horaire live vs match, détection player prop
// V17 : Safe hors tennis = mascotte.png pleine hauteur transparente
// V14 : Pas de logo tournoi (texte uniquement), cote pill fond rose néon
// V13 : Fix CORS logos, drapeaux flagcdn obligatoires
// ⚠️  NE JAMAIS exposer ce fichier publiquement
// ============================================================

if (!defined('ABSPATH')) { define('ABSPATH', true); }

// Clé API UNIQUEMENT dans claude-config.local.php (gitignored)
$__claude_local = __DIR__ . '/claude-config.local.php';
if (is_file($__claude_local)) {
    require_once $__claude_local;
}
if (!defined('CLAUDE_API_KEY')) {
    $k = getenv('CLAUDE_API_KEY');
    define('CLAUDE_API_KEY', (is_string($k) && $k !== '') ? $k : '');
}

define('CLAUDE_MODEL', 'claude-sonnet-4-6');
define('CLAUDE_THINKING_ENABLED', false);

// ============================================================
// ⚡ LIVE — Enrichissement JSON (template PHP rend le HTML)
// ============================================================
define('CLAUDE_LIVE_ENRICH_PROMPT', <<<'PROMPT'
Tu reçois les infos d'un match (sport, match, pronostic, cote). Tu réponds UNIQUEMENT par un objet JSON valide, sans aucun texte avant ou après, sans backticks.

🔴 RÈGLE HEURE — LIVE = HEURE DU POST, PAS DU MATCH
- time_fr pour un bet LIVE = heure de publication de la card = HEURE ACTUELLE (maintenant).
- Le serveur te donne la date+heure actuelle dans le prompt utilisateur. Utilise cette heure.
- Format HH:MM, timezone Europe/Paris.

🔴 RÈGLE COMPÉTITION — VÉRIFIE BIEN LA DIVISION ACTUELLE
- La "competition" doit refléter la DIVISION ACTUELLE (saison 2025-2026) des équipes, PAS leur division historique.
- Les clubs peuvent être promus/relégués d'une saison à l'autre. NE te fie PAS à tes souvenirs d'une saison passée.
- Si tu n'es pas sûr à 100%, choisis le nom neutre de la ligue nationale SANS division (ex: "La Liga" plutôt que "La Liga 2"). NEVER guess a division number.
- Exemples saison 2025-26 (à vérifier selon l'équipe):
  • Levante UD = La Liga (promu en 2025)
  • Villarreal, Sevilla, Valencia, Celta Vigo = La Liga
  • Espanyol, Getafe, Girona = La Liga
  • Sporting Gijón, Las Palmas, Leganés = La Liga 2 (Segunda)
- Format: "La Liga · J[numéro] · [stade]" ou "Premier League · J[N] · [stade]" etc.
- ⚠️ Si tu mets "La Liga 2" pour un match qui est en fait en La Liga, c'est un BUG CRITIQUE.

⚠️ Toutes les heures = Europe/Paris (UTC+1 hiver / UTC+2 été). Pour matchs US, convertir ET/PT vers Paris.

Structure de sortie OBLIGATOIRE :
{
  "date_fr": "Mercredi 22 Avril · 2026",
  "time_fr": "21:08",
  "sport": "tennis",
  "player1": "Djokovic N.",
  "player2": "Sinner J.",
  "flag1": "RS",
  "flag2": "IT",
  "team1_logo": "",
  "team2_logo": "",
  "competition": "Tennis ATP · Masters 1000 · Demi-finale",
  "badge_text": "Tennis · ATP",
  "n_edition": "348",
  "ghost": "LIVE",
  "kicker": "Chapitre cinq.",
  "pick_main": "Sinner remporte",
  "pick_accent": "le set 2.",
  "pick_market": "Marché live · Gagnant set en cours",
  "cote": "2.10",
  "confidence": 65,
  "value_pct": 12.7,
  "quote_main": "Break confirmé.",
  "quote_accent": "Momentum basculé.",
  "is_player_prop": false,
  "player_id": null,
  "player_name": "",
  "player_stats_hint": "",
  "opp_team": ""
}

RÈGLES CHAMPS :
- sport : "tennis", "foot", "basket", "nba", "hockey", "nhl", "baseball", "mlb" (minuscules)
- player1/player2 : noms exacts. Tennis "NOM P." (maj + initiale). Équipes "PARIS SG", "BOSTON CELTICS"…
- flag1/flag2 : code ISO2 pays (FR, GB, US, ES, IT, DE, RS…). Tennis toujours présent. Team sports tu peux laisser "" (le logo prime).
- team1_logo/team2_logo : laisse "" — le serveur PHP résout via ses helpers locaux.
- competition : nom complet (ex. "Ligue 1 · J29 · Parc des Princes")
- badge_text : "Tennis · ATP", "Foot · Ligue 1", "Basket · NBA", "Hockey · NHL"…
- n_edition : nombre à 3 chiffres (ex "348"). Si inconnu, génère un nombre cohérent 100-999.
- ghost : 3-5 caractères UPPERCASE (ex "L1", "ATP", "NBA", "LIVE", "DUEL"). Texte fantôme géant en arrière-plan.
- kicker : phrase éditoriale italique (2-4 mots). Ex "Dossier quatre.", "Chapitre cinq.", "Player Props.".
- pick_main : début du pronostic en Bebas Neue (ex "Sinner remporte", "Victoire PSG", "Denver couvre")
- pick_accent : fin du pronostic en italique, COMPLÈTE le pick_main (ex "le set 2.", "& BTTS Oui.", "+4.5 points.")
  → pick_main + pick_accent doivent former UNE phrase NATURELLE lue en entier
  ⚠️ NE PAS dupliquer le marché. Si pick_main = "Mbappé marque" → pick_accent = "n'importe quand." (PAS "& Marquer dans le match.")
  ⚠️ LONGUEUR MAX: 30-40 caractères au total (pick_main + pick_accent ensemble). Si dépasse, raccourcis.
  EXEMPLES OK: "Victoire PSG & BTTS Oui." | "Mbappé marque n'importe quand." | "Sinner remporte le set 2." | "Plus de 2.5 buts dans le match." | "Denver couvre +4.5 points."
  EXEMPLES KO: "Mbappé marque & Marquer dans le match." (redondant) | "BTTS BTTS Oui." (duplique)
- pick_market : ligne sous le pick, format "Marché · description" (ex "Marché live · Gagnant set en cours")
- cote : string "2.10" ou "2.30"
- confidence : entier 30-95 basé sur analyse (forme, H2H, contexte, absences)
- value_pct : value en %. Formule (proba estimée × cote - 1) × 100. Si négative → 0.
- quote_main : citation 2-5 mots (sans guillemets) — "Break confirmé.", "Le court ne ment pas."
- quote_accent : fin citation italique 2-4 mots — "Momentum basculé.", "La data non plus."

🎯 PLAYER PROP DETECTION (optionnel) :
Si le pronostic concerne UN SEUL joueur (buteur, SOT, total points NBA, points+rebonds+passes…) :
- is_player_prop: true
- player_id: ID du joueur (NBA=stats.nba.com id, NHL=nhl.com id, MLB=mlb.com id, Soccer=api-sports id)
- player_name: nom en MAJUSCULES (ex "MBAPPÉ", "HAALAND")
- player_stats_hint: court hint MAJUSCULES (ex "⚽ 28 BUTS · L1 · LOI DE L'EX")
- opp_team: équipe adverse (ex "OM", "BARCELONE", "LAKERS")
Si tu ne connais PAS l'ID exact → is_player_prop: false (mascotte par défaut).

JSON pur. Aucun texte avant/après. Aucun backtick.
PROMPT
);

// ============================================================
// 🎪 FUN — Enrichissement JSON (longshot)
// ============================================================
define('CLAUDE_FUN_ENRICH_PROMPT', <<<'PROMPT'
Tu reçois les infos d'un bet Fun (grosse cote, longshot). Tu réponds UNIQUEMENT par un objet JSON valide, sans texte avant/après, sans backticks.

🔴 HEURE : time_fr = HEURE DU COUP D'ENVOI du match (Europe/Paris), PAS l'heure actuelle.
Format HH:MM. Si inconnue, mets "20:00" par défaut.

🔴 RÈGLE COMPÉTITION — VÉRIFIE BIEN LA DIVISION ACTUELLE (saison 2025-2026)
- La "competition" doit refléter la DIVISION ACTUELLE des équipes, PAS leur division historique.
- Les clubs peuvent être promus/relégués. NE te fie PAS à tes souvenirs d'une saison passée.
- Si tu n'es pas sûr à 100%, choisis le nom neutre SANS division (ex: "La Liga" plutôt que "La Liga 2").
- Exemples 2025-26: Levante UD = La Liga (promu en 2025) • Sporting Gijón = La Liga 2.
- ⚠️ Mauvaise division = BUG CRITIQUE.

Structure de sortie OBLIGATOIRE :
{
  "date_fr": "Mercredi 22 Avril · 2026",
  "time_fr": "21:45",
  "sport": "foot",
  "player1": "Porto",
  "player2": "Benfica",
  "flag1": "PT",
  "flag2": "PT",
  "competition": "Primeira Liga · O Clássico · Estádio do Dragão",
  "badge_text": "Fun · Solo",
  "n_edition": "073",
  "ghost": "CRAZY",
  "kicker": "Volume I.",
  "pick_main": "Score exact",
  "pick_accent": "3-2 Porto.",
  "pick_market": "Marché spécial · Score exact",
  "cote": "15.00",
  "confidence": 22,
  "value_pct": 31.2,
  "quote_main": "La data dit non.",
  "quote_accent": "Le cœur dit oui."
}

RÈGLES (mêmes règles que LIVE pour les champs, adaptées au contexte FUN) :
- badge_text : "Fun · Solo", "Fun · Combi", "Hockey · NHL · Fun", "Tennis · Fun"…
- ghost : mot/chiffre qui évoque une grosse cote — "CRAZY", "×50", "×100", "COMMU"
- kicker : "Volume I.", "Volume II.", "Édition week-end."
- confidence : 20-45 (fun = longshot, confiance faible)
- cote : format "5.00" à "50.00" typiquement
- value_pct : souvent 15-35%
- quote_main/quote_accent : ton fun/rebelle — "On parie pour rire. On encaisse sérieux."

JSON pur uniquement.
PROMPT
);

// ============================================================
// 🛡️ SAFE — Enrichissement JSON (analyse validée)
// ============================================================
define('CLAUDE_SAFE_ENRICH_PROMPT', <<<'PROMPT'
Tu reçois un bet Safe (analyse validée, confiance forte). Tu réponds UNIQUEMENT par un objet JSON valide, sans texte avant/après, sans backticks.

🔴 HEURE : time_fr = HEURE DU COUP D'ENVOI (Europe/Paris). Si inconnue, trouve-la via les horaires officiels (Ligue 1 21h/17h, PL 16h/18h30, C1 21h, NBA ~01h-04h Paris, NHL ~01h-03h Paris…).

🔴 RÈGLE COMPÉTITION — VÉRIFIE BIEN LA DIVISION ACTUELLE (saison 2025-2026)
- La "competition" doit refléter la DIVISION ACTUELLE des équipes, PAS leur division historique.
- Les clubs peuvent être promus/relégués d'une saison à l'autre. NE te fie PAS à tes souvenirs d'une saison passée.
- Si tu n'es pas sûr à 100%, choisis le nom neutre de la ligue nationale SANS division (ex: "La Liga" plutôt que "La Liga 2"). NEVER guess a division number.
- Exemples 2025-26: Levante UD = La Liga (promu en 2025) • Real Sociedad, Valencia, Sevilla, Villarreal = La Liga • Sporting Gijón, Las Palmas, Leganés = La Liga 2.
- Format: "La Liga · J[numéro] · [stade]" ou "Premier League · J[N] · [stade]".
- ⚠️ Si tu mets une mauvaise division, c'est un BUG CRITIQUE.

Structure de sortie OBLIGATOIRE :
{
  "date_fr": "Mercredi 22 Avril · 2026",
  "time_fr": "21:00",
  "sport": "foot",
  "player1": "Paris SG",
  "player2": "Olympique Marseille",
  "flag1": "FR",
  "flag2": "FR",
  "competition": "Ligue 1 · J29 · Parc des Princes",
  "badge_text": "Foot · Ligue 1",
  "n_edition": "128",
  "ghost": "L1",
  "kicker": "Dossier douze.",
  "pick_main": "Victoire PSG",
  "pick_accent": "& BTTS Oui.",
  "pick_market": "Marché combiné · 1 + BTTS",
  "cote": "2.45",
  "confidence": 62,
  "value_pct": 9.1,
  "quote_main": "Deux camps. Une analyse.",
  "quote_accent": "Un pick.",
  "is_player_prop": false,
  "player_id": null,
  "player_name": "",
  "player_stats_hint": "",
  "opp_team": ""
}

RÈGLES (identiques à LIVE, adaptées SAFE) :
- confidence : 55-85 (Safe = confiance élevée)
- value_pct : typiquement 5-15%
- pick_main + pick_accent forment 1 phrase claire et NATURELLE ensemble
- kicker : ton éditorial sobre — "Dossier douze.", "Dossier quatre.", "Chapitre treize."
- quote_main/quote_accent : 2-5 mots chacun, ton premium ("Le court ne ment pas. La data non plus.")

⚠️ RÈGLE CRITIQUE pick_main / pick_accent : doivent former UNE phrase lisible, pas une duplication.
Le pick_accent complète ou nuance le pick_main, il ne répète PAS le marché.

EXEMPLES CORRECTS :
  ✅ Buteur : pick_main="Mbappé marque" + pick_accent="n'importe quand."
  ✅ Buteur : pick_main="Haaland buteur" + pick_accent="dès la 1ère mi-temps."
  ✅ Résultat : pick_main="Victoire PSG" + pick_accent="& BTTS Oui."
  ✅ Total : pick_main="Plus de 2.5 buts" + pick_accent="dans ce match."
  ✅ Handicap : pick_main="Real -1 HC" + pick_accent="à domicile."
  ✅ Set : pick_main="Djokovic gagne" + pick_accent="le set 2."
  ✅ NBA : pick_main="Denver couvre" + pick_accent="+4.5 points."

EXEMPLES INTERDITS (duplication/incohérence):
  ❌ pick_main="Mbappé marque" + pick_accent="& Marquer dans le match." (redondant + & bizarre)
  ❌ pick_main="Buteur Haaland" + pick_accent="buteur dans le match." (duplique "buteur")
  ❌ pick_main="BTTS" + pick_accent="BTTS Oui." (duplique)

LONGUEUR : pick_main + pick_accent doit tenir en ~30-40 caractères TOTAL.
Si tu dépasses, raccourcis pick_accent (ex: "dans le match." → "dans le 1.").

🎯 PLAYER PROP : Mêmes règles que LIVE. is_player_prop=true uniquement si tu as l'ID exact du joueur.

JSON pur uniquement.
PROMPT
);

// ============================================================
// 🎰 COMBI — Enrichissement JSON multi-picks
// ============================================================
define('CLAUDE_COMBI_ENRICH_PROMPT', <<<'PROMPT'
Tu reçois un combiné (2-5 picks avec cote totale). Tu réponds UNIQUEMENT par un objet JSON valide.

🔴 HEURE : time_fr = HEURE DU PREMIER MATCH du combiné (Europe/Paris).

🔴 RÈGLE COMPÉTITION — VÉRIFIE BIEN LA DIVISION ACTUELLE (saison 2025-2026)
- Si tu mentionnes la compétition dans les sélections, vérifie la division actuelle de CHAQUE équipe.
- Exemples 2025-26: Levante UD = La Liga (promu en 2025) • Sporting Gijón = La Liga 2.
- Si doute, utilise le nom neutre (ex: "La Liga" sans numéro de division).

Structure OBLIGATOIRE :
{
  "date_fr": "Mercredi 22 Avril · 2026",
  "time_fr": "20:45",
  "sport": "foot",
  "badge_text": "Multisports · Combi",
  "n_edition": "130",
  "ghost": "TRIO",
  "kicker": "Édition nº 08.",
  "cote": "6.68",
  "confidence": 52,
  "value_pct": 16.5,
  "quote_main": "Trois chocs.",
  "quote_accent": "Un ticket.",
  "selections": [
    {"team1": "Man City", "team2": "Liverpool", "flag1": "GB", "flag2": "GB", "prono": "Plus 2.5 buts", "cote": "1.72"},
    {"team1": "Inter", "team2": "Juventus", "flag1": "IT", "flag2": "IT", "prono": "BTTS Oui", "cote": "1.85"},
    {"team1": "Real Madrid", "team2": "Barcelone", "flag1": "ES", "flag2": "ES", "prono": "Victoire Real", "cote": "2.10"}
  ]
}

RÈGLES :
- cote (globale) = produit des cotes individuelles (ici 1.72 × 1.85 × 2.10 ≈ 6.68). Calcule et arrondis à 2 décimales.
- confidence globale : moyenne pondérée, typique 40-65 pour combi 3 picks.
- selections : array de 2 à 5 picks. Chaque pick a :
  - team1/team2 : noms équipes
  - flag1/flag2 : ISO2 pays
  - prono : pick en 2-5 mots (ex "Plus 2.5 buts", "BTTS Oui", "Victoire Real", "HC -1.5")
  - cote : cote individuelle format "1.72"
- badge_text : "Multisports · Combi", "Tennis · Combi", "Fun · Combi"
- ghost : "TRIO" (3 picks), "DUO" (2), "QUAD" (4)
- kicker : "Édition nº XX." ou "Édition week-end."

JSON pur uniquement.
PROMPT
);

// Alias pour compat avec generate-card.php (avant V18 utilisait CLAUDE_SAFE_COMBI_ENRICH_PROMPT)
if (!defined('CLAUDE_SAFE_COMBI_ENRICH_PROMPT')) {
    define('CLAUDE_SAFE_COMBI_ENRICH_PROMPT', CLAUDE_COMBI_ENRICH_PROMPT);
}

// ============================================================
// 🛡️ LEGACY — Ancien prompt HTML complet (backup rollback uniquement)
// ============================================================
define('CLAUDE_CARD_PROMPT', <<<'PROMPT'
[LEGACY V5 — conservé pour rollback uniquement, plus utilisé depuis V18]
PROMPT
);
