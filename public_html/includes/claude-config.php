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

🔴 RÈGLE COTE — UTILISE LA COTE FOURNIE PAR L'ADMIN
- L'admin te fournit une cote dans le prompt utilisateur (ex "Cote : 2.45").
- Tu DOIS utiliser CETTE cote exacte dans ta sortie JSON, sans la modifier.
- NE PAS inventer une cote. NE PAS mettre 1.50 par défaut.
- Si l'admin met "Cote : 2.45" → ta sortie : "cote": "2.45"
- Si l'admin met "Cote : 3.10" → ta sortie : "cote": "3.10"
- La cote peut être 1.25, 1.50, 2.45, 3.10, 5.00, 15.00, 50.00 — prends EXACTEMENT la valeur fournie.
- ⚠️ Mettre une cote différente de celle fournie = BUG CRITIQUE.

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

🎾 RÈGLE COMPÉTITION TENNIS — CIRCUITS DISTINCTS + TOURNOI PRÉCIS
- ATP Tour et ATP Challenger Tour sont 2 circuits DIFFÉRENTS. NE PAS mixer.
- ATP Tour (principal) = Grand Slam, Masters 1000, ATP 500, ATP 250
  → Format: "ATP · [nom tournoi]" ex "ATP · Rome Masters 1000", "ATP · Miami Open"
- ATP Challenger Tour (circuit secondaire, niveau en dessous) = tournois Challenger
  → Format: "ATP Challenger · [nom tournoi]" OU "Challenger Tour · [ville]"
  → JAMAIS "Tennis ATP · Challenger" (incohérent, mixe les circuits)
- ITF World Tennis Tour = niveau encore en dessous (tournois Futures / M15 / M25)
  → Format: "ITF · M25 [ville]" etc.
- WTA Tour / WTA 125 (challenger féminin) / ITF féminin = même logique
- Si tu vois "Challenger" dans le nom → c'est ATP/WTA Challenger, PAS ATP Tour principal.

🎾 RÈGLE TOURNOI PRÉCIS — OBLIGATOIRE, JAMAIS DE VAGUE
Tu as les 2 joueurs + la date du match (dans le prompt utilisateur) → tu DOIS identifier le nom EXACT du tournoi.

INTERDIT ABSOLU :
  ❌ "ATP · Tournoi en cours"  ❌ "Tournoi en cours"  ❌ "Tournoi ATP"
  ❌ "Tennis ATP" (sans nom)   ❌ "Match en cours"    ❌ "ATP Challenger · [ville au hasard]"

ÉTAPE 1 — ATP Tour (principal) OU ATP Challenger ?
Indice clé = CLASSEMENT des joueurs :
  • Si UN des 2 joueurs est top 100 ATP (ex Djokovic, Sinner, Alcaraz, Medvedev, Zverev, Rublev, Tsitsipas, Ruud, Fritz, Garin (~top 130-150), Rune, Dimitrov, Cerundolo, Draper, Auger-Aliassime, Khachanov…) → c'est probablement ATP Tour (principal)
  • Si les 2 joueurs sont hors top 150 → probablement Challenger ou ITF
  • ⚠️ En avril 2026, un Top 150 dans un tableau de Masters 1000 = ATP Tour, PAS Challenger.

ÉTAPE 2 — Identifier le tournoi exact via calendrier ATP 2026 :
  • 19 janv - 01 fév : Australian Open (Melbourne)
  • 09-22 mars : Indian Wells (BNP Paribas Open, Masters 1000)
  • 23 mars - 05 avr : Miami Open (Masters 1000)
  • 06-12 avr : Monte-Carlo Masters 1000
  • 13-19 avr : Barcelona Open (ATP 500) + ATP 250 Bucarest + Challengers divers
  • 20-26 avr : Madrid Masters Qualif/J1 + Challengers
  • 🔴 22 avr - 03 mai : **MADRID MASTERS 1000** (Mutua Madrid Open, terre battue)
  • 04-17 mai : Rome Masters 1000 (Internazionali BNL d'Italia)
  • 18-31 mai : Roland Garros qualifs puis main draw
  • 01-14 juin : Roland Garros + Stuttgart ATP 250
  • 15-28 juin : Queen's (ATP 500) + Halle (ATP 500) + Mallorca + Eastbourne
  • 29 juin - 12 juil : Wimbledon
  • Juil-Août : Hamburg, Kitzbuhel, Atlanta, Washington, Toronto/Montreal Masters, Cincinnati Masters
  • 24 août - 07 sept : US Open
  • Sept : Chengdu, Hangzhou, Zhuhai, Tokyo
  • Oct : Shanghai Masters, European indoor swing (Stockholm, Anvers, Bâle, Vienne)
  • Nov : Paris Masters, ATP Finals (Turin)

ÉTAPE 3 — Identifier la phase du tournoi selon les jours :
  • J1-J2 du tournoi = 1er tour / Round 1
  • J3-J4 = 2e tour / Round 2
  • J5-J6 = 1/8 de finale (si 64 joueurs) ou Round 3
  • J7-J8 = 1/4 de finale
  • J9-J10 = 1/2 finale
  • J11-J12 = Finale

EXEMPLES CORRECTS :
  ✅ "ATP Madrid Masters 1000 · 1er tour" (match le 23 avril 2026 pendant Madrid)
  ✅ "Monte-Carlo Masters 1000 · 1/8 de finale" (J5 du tournoi)
  ✅ "Roland Garros · 3e tour" (en fin de semaine 2)
  ✅ "ATP Barcelona Open 500 · Quart de finale"
  ✅ "ATP Challenger · Madrid · Demi-finale" (si vraiment Challenger)
  ✅ "ITF · M25 Santiago · 2nd tour"

Si tu n'es PAS sûr du niveau (ATP principal vs Challenger), regarde les classements :
  → 2 joueurs avec classement ATP ≤ 200 qui s'affrontent pendant la semaine d'un Masters 1000 = Masters 1000 (1er tour)
  → JAMAIS "Challenger" pour un match pendant une semaine de Masters 1000 sans preuve claire.

🔴 RÈGLE FUSEAU HORAIRE — TOUJOURS HEURE DE PARIS, JAMAIS HEURE LOCALE
time_fr doit TOUJOURS être en heure de Paris (Europe/Paris), PAS l'heure locale du stade/lieu du match.
⚠️ Pour les matchs US/Canada : TU DOIS CONVERTIR vers Paris avant de retourner time_fr.

Table de conversion USA → Paris (en période DST américain, de mars à novembre) :
  • ET (Eastern Time, ex: Miami, NY, Boston, Toronto, Atlanta)  = Paris -6h
    → Si match à 19:10 ET → Paris 01:10 (le lendemain)
    → Si match à 20:00 ET → Paris 02:00
    → Si match à 22:10 ET → Paris 04:10
  • CT (Central Time, ex: Chicago, Houston, Dallas, St Louis)   = Paris -7h
    → 19:10 CT → Paris 02:10 (lendemain)
    → 20:10 CT → Paris 03:10
  • MT (Mountain Time, ex: Denver, Phoenix, Salt Lake City, Utah)  = Paris -8h
    → 19:10 MT → Paris 03:10 (lendemain)
    → 19:30 MT → Paris 03:30 (lendemain)  ⚠️ Real Salt Lake = MT (Utah)
    → 20:00 MT → Paris 04:00 (lendemain)
  • PT (Pacific Time, ex: Los Angeles, San Francisco, Seattle)   = Paris -9h
    → 19:10 PT → Paris 04:10 (lendemain)
    → 22:10 PT → Paris 07:10 (lendemain)
    → 18:45 PT → Paris 03:45 (lendemain)

EXEMPLES CRITIQUES :
  ❌ MLB "San Francisco Giants vs Los Angeles Dodgers" à 22h10 → FAUX (c'est heure locale PT)
  ✅ Le match est à 18:45 PT (heure locale Oracle Park) → 03:45 Paris le lendemain → time_fr "03:45"
  ❌ MLS "Real Salt Lake vs Inter Miami CF" à 19:30 → FAUX (c'est heure MT au Rio Tinto Stadium, Utah)
  ✅ 19:30 MT → 03:30 Paris le lendemain → time_fr "03:30" (PAS 02:30)
  ❌ NBA "Lakers vs Warriors" à 19:30 → FAUX (heure locale PT)
  ✅ 19:30 PT → 04:30 Paris le lendemain → time_fr "04:30"
  ❌ NHL "Rangers vs Bruins" à 19:00 → FAUX (heure ET)
  ✅ 19:00 ET → 01:00 Paris lendemain → time_fr "01:00"

RÈGLE pratique pour tous sports US :
  • MLB/NBA/NHL/NFL/MLS : presque toujours début de soirée heure US = TRÈS TÔT le lendemain Paris (01h-07h)
  • Si tu calcules un time_fr avant 20h en étant en ET/PT/CT : tu as PROBABLEMENT oublié de convertir
  • Vérifie l'équipe à domicile pour savoir le fuseau (Dodgers/Giants = PT, Yankees/Red Sox = ET, Cubs = CT, Nuggets = MT)

⏰ HEURES EUROPÉENNES (foot UK/EU/Ligue 1/Bundesliga/La Liga/Serie A/etc.) :
  • Même fuseau que Paris (CET/CEST) pour: Bundesliga 🇩🇪, Ligue 1 🇫🇷, La Liga 🇪🇸, Serie A 🇮🇹,
    Eredivisie 🇳🇱, Jupiler 🇧🇪, Liga Portugal 🇵🇹, Süper Lig 🇹🇷 (même fuseau en CET)
  • Premier League 🇬🇧 : Londres = Paris -1h (17h UK = 18h Paris en été, 16h UK = 17h Paris en hiver)
  • NE PAS DEVINER l'heure — VÉRIFIE sur le tableau des matchs réels avant de remplir time_fr
  • Heures typiques Bundesliga : 15:30, 18:30, 20:30 (samedi) · 17:30, 19:30 (dimanche) · 20:30 (vendredi)
  • Heures typiques Ligue 1 : 17:00, 19:00, 21:00 · Premier League : 16:30, 18:30, 20:45 (Paris)
  • Si tu mets 20:00 ou 21:00 "par défaut" sans vérifier, tu vas te tromper 1 fois sur 2
  ⚠️ ERREUR RÉCURRENTE : Bundesliga à 20:30 mis en 20:00 — toujours vérifier le créneau exact (15:30/18:30/20:30)

Pour les matchs EU/UK/Afrique/Asie : garde l'heure locale si elle est déjà Europe/Paris OU convertis depuis UK (Paris +1h par rapport à UK en été).

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
  ⚠️ VÉRIFIE BIEN les nationalités tennis — erreurs fréquentes à NE PAS faire :
  • Julia Riera → AR (Argentine, née à Pergamino 🇦🇷) — PAS hongroise/paraguayenne/uruguayenne
  • Kayla Day → US (Américaine, née à Santa Barbara CA 🇺🇸) — PAS australienne/canadienne
  • Stefan Dostanic → US (Américain, né à Irvine CA 🇺🇸, USC/Wake Forest) — PAS serbe malgré le nom slave
  • Camila Osorio → CO (Colombienne)
  • María Lourdes Carle → AR (Argentine)
  • Nadia Podoroska → AR (Argentine)
  • Solana Sierra → AR (Argentine)
  • Francisca Jones → GB (Britannique, pas espagnole)
  • Whitney Osuigwe → US (Américaine, née en Floride)
  • Hailey Baptiste → US (Américaine)
  • Robin Montgomery → US (Américaine)
  • Katie Volynets → US (Américaine malgré nom slave)
  • Varvara Gracheva → FR (française depuis 2023, PAS russe)
  • Aliaksandra Sasnovich → BY (Biélorusse)
  • Anastasia Potapova → RU (Russe)
  ⚠️ PIÈGE NOMS SLAVES/EUROPÉENS → nationalité US :
  Beaucoup de joueurs/joueuses US ont des noms d'origine serbe, russe, ukrainienne,
  polonaise, croate, etc. mais sont 100% américains (nés aux USA, représentent USA).
  NE JUGE PAS la nationalité au son du nom — vérifie la birthplace + federation.
  Si tu as un doute sur une joueuse, écris son pays en clair dans un commentaire mental
  mais NE PAS deviner le flag — vérifie sur WTA/ITF/ATP/Wikipedia avant.
- team1_logo/team2_logo : laisse "" — le serveur PHP résout via ses helpers locaux.
- competition : nom complet (ex. "Ligue 1 · J29 · Parc des Princes")
- badge_text : "Tennis · ATP", "Tennis · Challenger", "Tennis · ITF", "Tennis · WTA", "Foot · Ligue 1", "Foot · La Liga", "Basket · NBA", "Hockey · NHL"…
  → Pour tennis Challenger, écris "Tennis · Challenger" (PAS "Tennis · ATP")
  → Pour ITF Futures, écris "Tennis · ITF"
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
- cote : string format "2.10" — DOIT correspondre EXACTEMENT à la cote fournie par l'admin (voir RÈGLE COTE)
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

🔴 RÈGLE COTE — UTILISE LA COTE FOURNIE PAR L'ADMIN
- Si l'admin fournit une cote explicite (ex "Cote : 15.00") → utilise EXACTEMENT cette valeur.
- Si l'admin ne fournit qu'une liste de paris combinés, calcule la cote totale (produit des cotes).
- NE PAS inventer une cote arbitraire.

🔴 HEURE : time_fr = HEURE DU COUP D'ENVOI du match en HEURE DE PARIS (Europe/Paris), JAMAIS l'heure locale.
Format HH:MM. Si inconnue, mets "20:00" par défaut.

🔴 CONVERSION FUSEAU US → PARIS (matchs MLB/NBA/NHL/NFL/MLS)
  • ET (Miami/NY/Boston/Toronto/Atlanta)  = Paris -6h (19:10 ET → 01:10 Paris lendemain)
  • CT (Chicago/Houston/Dallas/St Louis)   = Paris -7h
  • MT (Denver/Phoenix/Salt Lake City)     = Paris -8h
  • PT (LA/San Francisco/Seattle)          = Paris -9h (22:10 PT → 07:10 Paris lendemain, 18:45 PT → 03:45)
⚠️ Si tu retournes un time_fr avant 20h pour un match US, tu as oublié de convertir.
Exemple: MLB Giants vs Dodgers à 18:45 PT → time_fr "03:45" (PAS "22:10" qui est l'heure locale).

🔴 RÈGLE COMPÉTITION — VÉRIFIE BIEN LA DIVISION ACTUELLE (saison 2025-2026)
- La "competition" doit refléter la DIVISION ACTUELLE des équipes, PAS leur division historique.
- Les clubs peuvent être promus/relégués. NE te fie PAS à tes souvenirs d'une saison passée.
- Si tu n'es pas sûr à 100%, choisis le nom neutre SANS division (ex: "La Liga" plutôt que "La Liga 2").
- Exemples 2025-26: Levante UD = La Liga (promu en 2025) • Sporting Gijón = La Liga 2.
- ⚠️ Mauvaise division = BUG CRITIQUE.

🔴 DÉTECTION SOLO vs COMBI — LIS L'INPUT ATTENTIVEMENT
L'admin peut te donner SOIT un seul pari, SOIT plusieurs lignes à combiner.
- SI l'input contient UNE SEULE ligne / un seul pari → structure SOLO (player1/player2/pick_main/pick_accent)
- SI l'input contient PLUSIEURS LIGNES (plusieurs matches + picks + cotes) → structure COMBI (array "bets")
  ⚠️ Chaque ligne = 1 pari distinct, JAMAIS concaténer les joueurs entre lignes
  Exemple input COMBI (3 lignes séparées):
    Ligne 1: Garin - Blockx - Vainqueur Garin - 2.60
    Ligne 2: Hanfmann - Giron - Vainqueur Hanfmann - 1.36
    Ligne 3: Atmane - Kecmanovic - Vainqueur Atmane - 2.21
  → OBLIGATOIRE : 3 objets dans "bets", jamais "Garin · Hanfmann · Atmane" en un seul bet.

═══ STRUCTURE SOLO (1 seul pari) ═══
{
  "date_fr": "Mercredi 22 Avril · 2026",
  "time_fr": "21:45",
  "sport": "foot",
  "player1": "Porto",
  "player2": "Benfica",
  "flag1": "PT", "flag2": "PT",
  "competition": "Primeira Liga · O Clássico",
  "badge_text": "Fun · Solo",
  "n_edition": "073", "ghost": "CRAZY", "kicker": "Volume I.",
  "pick_main": "Score exact", "pick_accent": "3-2 Porto.",
  "pick_market": "Marché spécial · Score exact",
  "cote": "15.00", "confidence": 22, "value_pct": 31.2,
  "quote_main": "La data dit non.", "quote_accent": "Le cœur dit oui."
}

═══ STRUCTURE COMBI (2+ paris combinés) ═══
{
  "date_fr": "Jeudi 23 Avril · 2026",
  "time_fr": "13:00",
  "sport": "tennis",
  "badge_text": "Tennis · Fun",
  "n_edition": "072", "ghost": "CRAZY", "kicker": "Fun Zone.",
  "confidence": 28, "value_pct": 18.5,
  "quote_main": "La cote bombe.", "quote_accent": "On parie pour rire.",
  "bets": [
    {"player1": "Garin, Cristian", "player2": "Alexander Blockx", "flag1": "CL", "flag2": "BE", "prono": "Vainqueur Garin", "cote": "2.60", "heure": "13:00"},
    {"player1": "Hanfmann, Yannick", "player2": "Giron, Marcos", "flag1": "DE", "flag2": "US", "prono": "Vainqueur Hanfmann", "cote": "1.36", "heure": "14:30"},
    {"player1": "Atmane, Terence", "player2": "Kecmanovic, Miomir", "flag1": "FR", "flag2": "RS", "prono": "Vainqueur Atmane", "cote": "2.21", "heure": "16:00"}
  ]
}

RÈGLES CHAMPS COMMUNS :
- badge_text : "Fun · Solo" (1 pari) OU "[Sport] · Fun" / "Fun · Combi" (multi)
- ghost : "CRAZY", "×50", "×100", "COMMU", "TRIO" (3 picks), "DUO" (2), "QUAD" (4)
- kicker : "Volume I.", "Fun Zone.", "Édition week-end."
- confidence : 20-45 (fun = longshot, confiance faible)
- quote_main/quote_accent : ton fun/rebelle — "On parie pour rire. On encaisse sérieux."

RÈGLES COMBI spécifiques :
- "bets" = array de 2 à 5 paris. JAMAIS 1 seul bet dans un combi. JAMAIS concaténer les joueurs.
- Chaque bet a: player1, player2, flag1 (ISO2), flag2 (ISO2), prono (court ex "Vainqueur X"), cote (string), heure
- La "cote" globale (hors bets) n'est PAS obligatoire en combi — le serveur calcule le produit.
- Pas de player1/player2/pick_main au niveau racine quand "bets" est présent.

JSON pur uniquement.
PROMPT
);

// ============================================================
// 🛡️ SAFE — Enrichissement JSON (analyse validée)
// ============================================================
define('CLAUDE_SAFE_ENRICH_PROMPT', <<<'PROMPT'
Tu reçois un bet Safe (analyse validée, confiance forte). Tu réponds UNIQUEMENT par un objet JSON valide, sans texte avant/après, sans backticks.

🔴 RÈGLE COTE — UTILISE LA COTE FOURNIE PAR L'ADMIN (voir prompt utilisateur "Cote : X.XX")
- La cote dans ta sortie JSON DOIT être EXACTEMENT celle fournie par l'admin.
- Si l'admin met "Cote : 2.45" → ta sortie : "cote": "2.45"
- NE PAS inventer. NE PAS mettre 1.50 par défaut. NE PAS arrondir.
- ⚠️ Mauvaise cote = BUG CRITIQUE.

🔴 HEURE : time_fr = HEURE DU COUP D'ENVOI en HEURE DE PARIS (Europe/Paris), JAMAIS l'heure locale du stade.
Si inconnue, trouve-la via les horaires officiels (Ligue 1 21h/17h, PL 16h/18h30, C1 21h).

🔴 CONVERSION FUSEAU US → PARIS (OBLIGATOIRE pour MLB/NBA/NHL/NFL/MLS)
  • ET (Miami/NY/Boston/Toronto/Atlanta)  = Paris -6h (19:10 ET → 01:10 Paris lendemain)
  • CT (Chicago/Houston/Dallas/St Louis)   = Paris -7h (20:10 CT → 03:10 Paris)
  • MT (Denver/Phoenix/Salt Lake City)     = Paris -8h
  • PT (LA/San Francisco/Seattle)          = Paris -9h (22:10 PT → 07:10 Paris, 18:45 PT → 03:45)
⚠️ Si tu retournes un time_fr avant 20h pour un match US, tu as oublié de convertir.
Exemple: MLB Giants vs Dodgers à 18:45 PT → time_fr "03:45" (PAS "22:10" qui est l'heure locale).
Équipes PT: Giants, Dodgers, Angels, Padres, Mariners, Athletics, Warriors, Lakers, Clippers, Kings, Ducks, Sharks, LA Galaxy, LAFC, Seattle Sounders, Portland Timbers, Vancouver Whitecaps, San Jose Earthquakes.
Équipes ET: Yankees, Red Sox, Mets, Phillies, Braves, Orioles, Rays, Blue Jays, Heat, Celtics, Knicks, 76ers, Rangers, Bruins, Maple Leafs, Inter Miami, Atlanta United, Columbus Crew, DC United, Toronto FC, New England Revolution, New York City FC, NY Red Bulls, Orlando City, Charlotte FC, Nashville SC, Philadelphia Union, CF Montreal, FC Cincinnati.
Équipes CT: Cubs, White Sox, Cardinals, Astros, Rangers, Royals, Twins, Brewers, Bulls, Mavericks, Blackhawks, Wild, Chicago Fire, Houston Dynamo, FC Dallas, Sporting KC, Minnesota United, Austin FC, St Louis City SC.
Équipes MT (Mountain Time = Paris -8h): Rockies, Nuggets, Jazz, Avalanche, Coyotes, **Real Salt Lake (Utah)**, Colorado Rapids.

🔴 RÈGLE COMPÉTITION — VÉRIFIE BIEN LA DIVISION ACTUELLE (saison 2025-2026)
- La "competition" doit refléter la DIVISION ACTUELLE des équipes, PAS leur division historique.
- Les clubs peuvent être promus/relégués d'une saison à l'autre. NE te fie PAS à tes souvenirs d'une saison passée.
- Si tu n'es pas sûr à 100%, choisis le nom neutre de la ligue nationale SANS division (ex: "La Liga" plutôt que "La Liga 2"). NEVER guess a division number.
- Exemples 2025-26: Levante UD = La Liga (promu en 2025) • Real Sociedad, Valencia, Sevilla, Villarreal = La Liga • Sporting Gijón, Las Palmas, Leganés = La Liga 2.
- Format: "La Liga · J[numéro] · [stade]" ou "Premier League · J[N] · [stade]".
- ⚠️ Si tu mets une mauvaise division, c'est un BUG CRITIQUE.

🎾 RÈGLE COMPÉTITION TENNIS — CIRCUITS DISTINCTS
- ATP Tour et ATP Challenger Tour = 2 circuits DIFFÉRENTS. JAMAIS les mixer.
- ATP Tour → "ATP · [tournoi]" ex "ATP · Rome Masters 1000", "ATP · Monte-Carlo"
- ATP Challenger Tour → "ATP Challenger · [ville]" ex "ATP Challenger · Madrid"
- ITF Futures → "ITF · M25 Biarritz" etc.
- WTA / WTA 125 / ITF féminin = même logique
- Si "Challenger" dans le nom → ATP/WTA Challenger, PAS ATP Tour principal.
- ⚠️ "Tennis ATP · Challenger" = BUG (mixe ATP principal + circuit Challenger). Utilise "ATP Challenger · [tournoi]".

🎾 RÈGLE TOURNOI PRÉCIS — OBLIGATOIRE, JAMAIS DE VAGUE
Tu as les 2 joueurs + la date du match → tu DOIS identifier le nom EXACT du tournoi.

INTERDIT ABSOLU :
  ❌ "ATP · Tournoi en cours"  ❌ "Tournoi en cours"  ❌ "Tournoi ATP"
  ❌ "Tennis ATP" (sans nom)   ❌ "Match en cours"    ❌ "ATP Challenger · [ville au hasard]"

ÉTAPE 1 — ATP Tour (principal) OU ATP Challenger ?
Indice clé = CLASSEMENT des joueurs :
  • Si UN des 2 joueurs est top 150 ATP (ex Djokovic, Sinner, Alcaraz, Medvedev, Zverev, Rublev, Tsitsipas, Ruud, Fritz, Garin (~top 130-150), Rune, Dimitrov, Cerundolo, Draper, Auger-Aliassime, Khachanov…) → c'est probablement ATP Tour (principal)
  • Si les 2 joueurs sont hors top 200 → probablement Challenger ou ITF
  • ⚠️ En avril 2026, un Top 150 dans un tableau de Masters 1000 = ATP Tour, PAS Challenger.

ÉTAPE 2 — Identifier le tournoi exact via calendrier ATP 2026 :
  • 19 janv - 01 fév : Australian Open (Melbourne)
  • 09-22 mars : Indian Wells (BNP Paribas Open, Masters 1000)
  • 23 mars - 05 avr : Miami Open (Masters 1000)
  • 06-12 avr : Monte-Carlo Masters 1000
  • 13-19 avr : Barcelona Open (ATP 500) + ATP 250 Bucarest + Challengers
  • 🔴 22 avr - 03 mai : **MADRID MASTERS 1000** (Mutua Madrid Open, terre battue)
  • 04-17 mai : Rome Masters 1000 (Internazionali BNL d'Italia)
  • 18 mai - 07 juin : Roland Garros (qualifs + main draw)
  • 15-28 juin : Queen's (ATP 500) + Halle (ATP 500) + Mallorca
  • 29 juin - 12 juil : Wimbledon
  • Juil-Août : Hamburg, Kitzbuhel, Atlanta, Washington, Toronto/Montreal Masters, Cincinnati Masters
  • 24 août - 07 sept : US Open
  • Sept-Oct : Asian swing (Chengdu, Tokyo, Shanghai Masters)
  • Oct-Nov : European indoor (Stockholm, Anvers, Bâle, Vienne, Paris Masters, ATP Finals Turin)

ÉTAPE 3 — Identifier la phase du tournoi selon les jours depuis le début :
  • J1-J2 = 1er tour • J3-J4 = 2e tour • J5-J6 = 1/8 de finale ou Round 3
  • J7-J8 = 1/4 de finale • J9-J10 = 1/2 finale • J11-J12 = Finale

EXEMPLES CORRECTS :
  ✅ "ATP Madrid Masters 1000 · 1er tour" (match le 23 avril 2026 pendant Madrid)
  ✅ "Monte-Carlo Masters 1000 · 1/8 de finale" (J5 du tournoi)
  ✅ "Roland Garros · 3e tour"
  ✅ "ATP Barcelona Open 500 · Quart de finale"
  ✅ "ATP Challenger · Madrid · Demi-finale" (si vraiment Challenger)

Si 2 joueurs avec classement ATP ≤ 200 s'affrontent pendant la semaine d'un Masters 1000 = Masters 1000 (1er tour).
JAMAIS "Challenger" pour un match pendant une semaine de Masters 1000 sans preuve claire.

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

🔴 HEURE : time_fr = HEURE DU PREMIER MATCH du combiné en HEURE DE PARIS (Europe/Paris).

🔴 CONVERSION FUSEAU US → PARIS pour MLB/NBA/NHL/NFL/MLS (OBLIGATOIRE)
  • ET (NY/Boston/Miami) = Paris -6h | CT (Chicago/Houston) = Paris -7h
  • MT (Denver/Phoenix)  = Paris -8h | PT (LA/SF/Seattle)  = Paris -9h
⚠️ Ne jamais mettre l'heure locale US. Ex: match 22:10 PT → time_fr "07:10" le lendemain.

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
