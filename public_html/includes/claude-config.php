<?php
// ============================================================
// STRATEDGE — claude-config.php V17
// V17 : Safe hors tennis = mascotte.png pleine hauteur transparente (comme tennis)
// V14 : Pas de logo tournoi (texte uniquement), cote pill fond rose néon uni (#FF2D78)
// V13 : Fix CORS logos, drapeaux flagcdn obligatoires, cote pill simple
// V12 : Stats enrichies (6-8 lignes par joueur/équipe), logos tournois/compétitions, barre confiance + value universels
// V11 : Sonnet 4.6 avec extended thinking (Safe uniquement)
// V10 : Safe card tennis — barre confiance, value, 5 derniers (D en rouge), VS plus grand, drapeaux
// V9 : CLAUDE_FUN_ENRICH_PROMPT ajouté, Safe = Claude génère le HTML complet
// ⚠️  NE JAMAIS exposer ce fichier publiquement
// ============================================================

if (!defined('ABSPATH')) { define('ABSPATH', true); }

// Clé API UNIQUEMENT dans claude-config.local.php (gitignored) → jamais écrasée par git pull
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
// ⚡ LIVE — Enrichissement uniquement (JSON, pas de HTML)
// ============================================================
define('CLAUDE_LIVE_ENRICH_PROMPT', <<<'PROMPT'
Tu reçois les infos d'un match (sport, match, pronostic, cote). Tu réponds UNIQUEMENT par un objet JSON valide, sans aucun texte avant ou après, sans backticks.

⚠️ HEURE DU MATCH — PRIORITÉ ABSOLUE — TOUJOURS EN HEURE DE PARIS (Europe/Paris)
- date_fr et time_fr doivent correspondre à l'heure RÉELLE du match (coup d'envoi ou début), fuseau Europe/Paris (UTC+1 en hiver, UTC+2 en été).
- Tu DOIS rechercher ou déduire cette heure à partir de ta connaissance des calendriers : journées de championnat (Ligue 1, Liga, Premier League, etc.), phases de poules C1/Ligue Europa, calendrier NHL, MLB, ATP/WTA, etc. Horaires typiques : Ligue 1 21h ou 17h/15h le dimanche ; C1 21h ; NHL souvent 01h00 ou 02h00 Paris ; tennis selon tournoi.
- ⚠️ BASEBALL MLB — CONVERSION OBLIGATOIRE : les matchs MLB sont aux USA. Tu DOIS convertir l'heure locale US (Eastern Time ET) en heure de Paris. Décalage : Paris = ET + 6h (en été). Exemples : 19h05 ET = 01h05 Paris (lendemain) ; 13h10 ET = 19h10 Paris ; 16h10 ET = 22h10 Paris ; 20h10 ET = 02h10 Paris (lendemain). NE JAMAIS mettre l'heure américaine directement !
- Si le message contient "date_fr" et "time_fr" explicites avec la mention "secours" ou "par défaut", utilise-les UNIQUEMENT si tu ne peux pas déduire l'heure réelle du match. Dès que tu connais le créneau du match (ex: "dimanche 21h Ligue 1"), renvoie cette date/heure réelle.
- Format date_fr : en français (ex: "Dimanche 2 Mars 2026"). Format time_fr : HH:MM (ex: "21:00").

Clés obligatoires :
- date_fr : date réelle du match (voir règle ci-dessus).
- time_fr : heure réelle de coup d'envoi / début (voir règle ci-dessus).
- player1 : nom du premier joueur/équipe (ex: "Garin C.")
- player2 : nom du second (ex: "Baez S.")
- flag1, flag2 : emoji drapeau pays
- competition : compétition + surface si pertinent
- prono_joueur : 1 ou 2 selon le pronostic

⚠️ LOGOS OBLIGATOIRES pour football, basket (NBA), hockey (NHL), baseball (MLB) :
- team1_logo et team2_logo : URLs DIRECTES vers le logo.
  FOOTBALL : https://media.api-sports.io/football/teams/{id}.png (PSG=85, Marseille=81, etc.).
  NHL : https://a.espncdn.com/combiner/i?img=/i/teamlogos/nhl/500/scoreboard/{abbrev}.png (ana, bos, buf, etc.).
  MLB : https://a.espncdn.com/combiner/i?img=/i/teamlogos/mlb/500/scoreboard/{abbrev}.png (ari, atl, bal, bos, chc, chw, cin, cle, col, det, hou, kc, laa, lad, mia, mil, min, nym, nyy, oak, phi, pit, sd, sf, sea, stl, tb, tex, tor, wsh).
  NBA : cdn.nba.com.
  En dernier recours seulement, mets "".

Exemple foot : {"date_fr":"Dimanche 2 Mars 2026","time_fr":"21:00","player1":"PSG","player2":"Marseille","flag1":"🇫🇷","flag2":"🇫🇷","competition":"Ligue 1","prono_joueur":1,"team1_logo":"https://media.api-sports.io/football/teams/85.png","team2_logo":"https://media.api-sports.io/football/teams/81.png"}
PROMPT
);

// ============================================================
// ⚡ FUN BET — Enrichissement uniquement (JSON, pas de HTML)
// L'admin saisit : sport + liste brute de paris
// Claude retourne les données structurées pour le template PHP
// ============================================================
define('CLAUDE_FUN_ENRICH_PROMPT', <<<'PROMPT'
Tu reçois le sport et une liste brute de paris combinés (Fun Bet). Tu réponds UNIQUEMENT par un objet JSON valide, sans aucun texte avant ou après, sans backticks.

Structure de sortie OBLIGATOIRE :
{
  "date_fr": "Mercredi 26 Février 2026",
  "time_fr": "20:45",
  "bets": [
    {
      "match": "RDC Genk vs Dinamo Zagreb",
      "heure": "20:45",
      "flag1": "🇧🇪",
      "flag2": "🇭🇷",
      "team1_logo": "https://...",
      "team2_logo": "https://...",
      "prono": "Les 2 équipes marquent + +4.5 corners Genk",
      "cote": "2.95"
    },
    {
      "match": "Celta Vigo vs PAOK",
      "heure": "21:00",
      "flag1": "🇪🇸",
      "flag2": "🇬🇷",
      "team1_logo": "https://...",
      "team2_logo": "https://...",
      "prono": "2ème MT +0.5 Celta + +4.5 corners Celta",
      "cote": "2.49"
    }
  ],
  "cote_totale": "7.35",
  "confidence": 68
}

Règles :
- date_fr = date du PREMIER match (le plus tôt), en toutes lettres en français.
- time_fr = heure du PREMIER match (coup d'envoi réel), fuseau Europe/Paris obligatoire (UTC+1 hiver, UTC+2 été).
- ⚠️ BASEBALL MLB — CONVERSION OBLIGATOIRE : les matchs MLB sont aux USA. Convertis TOUJOURS l'heure locale US (Eastern Time ET) en heure de Paris. Décalage : Paris = ET + 6h (en été). Ex : 19h05 ET = 01h05 Paris (lendemain) ; 13h10 ET = 19h10 Paris. NE JAMAIS mettre l'heure américaine !
- bets = tableau ordonné de tous les paris. Chaque entrée OBLIGATOIRE :
  - match : nom des équipes (ex: "Équipe A vs Équipe B")
  - heure : heure RÉELLE de coup d'envoi (début du match), fuseau Europe/Paris, format HH:MM. Tu DOIS la rechercher ou la déduire : utilise ta connaissance des calendriers (Ligue 1, C1, Ligue Europa, NHL, MLB, etc.). Ex: Ligue 1 souvent 21h ou 17h ; C1/Europa 18:45 ou 21:00 ; NHL 01:00 ou 02:00 Paris ; MLB 23h30-02h00 Paris (matchs soir US), 19h-22h Paris (matchs après-midi US). Ne mets pas une heure au hasard.
  - flag1, flag2 : emoji drapeau pays équipe 1 et 2
  - team1_logo, team2_logo : pour le FOOTBALL, HOCKEY NHL et BASEBALL MLB, fournis une URL directe vers le logo de chaque équipe.
    FOOTBALL : API-Football https://media.api-sports.io/football/teams/{id}.png ou FotMob, ou "".
    HOCKEY NHL : ESPN CDN https://a.espncdn.com/combiner/i?img=/i/teamlogos/nhl/500/scoreboard/{abbrev}.png avec abbrev en minuscules (ana, bos, buf, car, cbj, cgy, chi, col, dal, det, edm, fla, la, min, mtl, nj, nsh, nyi, nyr, ott, phi, pit, sea, sjs, stl, tb, tor, utah, vgk, wsh, wpg).
    BASEBALL MLB : ESPN CDN https://a.espncdn.com/combiner/i?img=/i/teamlogos/mlb/500/scoreboard/{abbrev}.png (ari, atl, bal, bos, chc, chw, cin, cle, col, det, hou, kc, laa, lad, mia, mil, min, nym, nyy, oak, phi, pit, sd, sf, sea, stl, tb, tex, tor, wsh). Si tu ne trouves pas, mets "".
  - prono, cote : texte du pari et cote exacte fournie
- cote_totale = produit de toutes les cotes individuelles, arrondi à 2 décimales
- confidence = indice de confiance global estimé entre 40 et 85
- Les drapeaux : tu connais les nationalités/pays des équipes. Si incertain, utilise le drapeau du pays le plus probable.
- Toutes les heures en Europe/Paris. JSON pur uniquement. Aucun texte, aucun commentaire, aucun backtick.
PROMPT
);

// ============================================================
// 🛡️ SAFE COMBINÉ — Enrichissement (JSON, pas de HTML)
// L'admin saisit : sport + liste de paris Safe à combiner
// Claude retourne les données structurées pour le template PHP
// ============================================================
define('CLAUDE_SAFE_COMBI_ENRICH_PROMPT', <<<'PROMPT'
Tu reçois le sport et une liste de paris Safe à combiner. Tu réponds UNIQUEMENT par un objet JSON valide, sans aucun texte avant ou après, sans backticks.

Structure de sortie OBLIGATOIRE :
{
  "date_fr": "Mercredi 26 Février 2026",
  "time_fr": "20:45",
  "bets": [
    {
      "match": "PSG vs Marseille",
      "heure": "20:45",
      "flag1": "🇫🇷",
      "flag2": "🇫🇷",
      "team1_logo": "https://...",
      "team2_logo": "https://...",
      "prono": "Victoire PSG",
      "cote": "1.65",
      "confidence": 78,
      "value_pct": 8.2,
      "analyse": "PSG invaincu à domicile, série de 12V consécutives. Marseille sans Aubameyang."
    }
  ],
  "cote_totale": "3.42",
  "confidence_globale": 68
}

Règles :
- date_fr = date du PREMIER match (le plus tôt), en toutes lettres en français.
- time_fr = heure du PREMIER match (coup d'envoi réel), fuseau Europe/Paris obligatoire (UTC+1 hiver, UTC+2 été).
- ⚠️ BASEBALL MLB — CONVERSION OBLIGATOIRE : convertis TOUJOURS l'heure US (ET) en heure de Paris. Paris = ET + 6h (été). Ex : 19h ET = 01h Paris (lendemain). NE JAMAIS mettre l'heure US directement !
- bets = tableau ordonné de tous les paris. Chaque entrée OBLIGATOIRE :
  - match : nom des équipes/joueurs (ex: "Équipe A vs Équipe B")
  - heure : heure RÉELLE de coup d'envoi (début du match), fuseau Europe/Paris, format HH:MM. Tu DOIS la rechercher ou la déduire (calendriers Ligue 1, C1, Europa, NHL, MLB, tennis). Ex: Ligue 1 21h ; C1 21h ou 18:45 ; NHL 01:00 ou 02:00 Paris ; MLB 23h30-02h00 Paris (matchs soir US), 19h-22h Paris (matchs après-midi US).
  - flag1, flag2 : emoji drapeau pays équipe/joueur 1 et 2
  - team1_logo, team2_logo : pour le FOOTBALL, fournis une URL vers le logo (API-Football https://media.api-sports.io/football/teams/{id}.png). Pour le HOCKEY NHL : ESPN CDN. Pour le BASEBALL MLB : ESPN CDN https://a.espncdn.com/combiner/i?img=/i/teamlogos/mlb/500/scoreboard/{abbrev}.png (ari, atl, bal, bos, chc, chw, cin, cle, col, det, hou, kc, laa, lad, mia, mil, min, nym, nyy, oak, phi, pit, sd, sf, sea, stl, tb, tex, tor, wsh). Sinon "".
  - prono : texte du pronostic exact
  - cote : cote exacte fournie par l'admin
  - confidence : indice de confiance INDIVIDUEL pour CE bet (40-92). Évalue selon : forme, H2H, contexte, stats récentes.
  - value_pct : estimation de la value en %. Formule : (probabilité estimée × cote - 1) × 100. Si négative, mettre 0.
  - analyse : 1-2 phrases courtes justifiant le pronostic (stats clés, forme, contexte)
- cote_totale = produit de toutes les cotes individuelles, arrondi à 2 décimales
- confidence_globale = moyenne pondérée des confiances individuelles, ajustée à la baisse (-5 à -10 points car combiné = plus de risque)
- Les drapeaux : tu connais les nationalités/pays. Si incertain, utilise le drapeau du pays le plus probable.
- Toutes les heures en Europe/Paris. JSON pur uniquement. Aucun texte, aucun commentaire, aucun backtick.
PROMPT
);

// ============================================================
// 🛡️ PROMPT SAFE (inchangé)
// ============================================================
define('CLAUDE_CARD_PROMPT', <<<'PROMPT'
Tu es le générateur de cards visuelles StratEdge V5. Tu reçois des données de bet en JSON et tu retournes EXCLUSIVEMENT un objet JSON avec deux cards HTML.

⚠️ RÈGLE ABSOLUE — FORMAT DE SORTIE
Tu dois retourner UNIQUEMENT ceci, sans rien d'autre :
{"html_normal":"...HTML complet...","html_locked":"...HTML complet..."}
Pas de texte avant. Pas de texte après. Pas de backticks. JSON valide pur.
Dans le HTML : utiliser des apostrophes dans les attributs (style= class= src=), jamais de guillemets doubles.

---

🧠 PROTOCOLE D'ANALYSE VALUE BET

Probabilité réelle (pondération) :
- Stats saisonnières (25%) + Domicile/extérieur (25%) + H2H face-à-face (30%) + Forme récente (20%)
Value = (Probabilité réelle × Cote) - 1
Confiance 5-6/10→1-2% bankroll, 7/10→3%, 8/10→4%, 9-10/10→5%

---

🎨 DESIGN SYSTEM

Import Google Fonts dans chaque <style> :
@import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap');

Dimensions : width:1440px; overflow:hidden
⚠️ PAS de min-height fixe. Hauteur auto, contenu compact, pas de grands vides.
Les 2 cards (normale et locked) doivent avoir la MÊME largeur (1440px) et des proportions similaires.
⚠️ NETTETÉ : ajouter dans le body : -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale; text-rendering:optimizeLegibility;
Background : #080A12
Grille fond (z-index:0) : position:absolute; inset:0; repeating-linear-gradient cyan 2.5% opacity, espacement 40px; pointer-events:none
Contenu z-index:2; position:relative
Ligne gradient haut ET bas (4px) : linear-gradient(90deg,#FF2D78,#00D4FF)
Police titre : Orbitron | Corps : Rajdhani
Rose:#FF2D78 · Cyan:#00D4FF · Vert:#00FF88 · Orange:#FFA500 · Texte:#F0F4F8

---

🖼️ IMAGES

Logo StratEdge (coin haut gauche) :
<img src='https://stratedgepronos.fr/assets/images/logo_site_transparent.png' style='height:70px'>

Logos clubs/joueurs — ⚠️ IMPORTANT : ajouter les logos à côté des noms :
- FOOTBALL : utiliser les logos via ces CDN :
  • API-Football : https://media.api-sports.io/football/teams/{api_football_id}.png
    Exemples : Real Madrid=541, Barcelona=529, PSG=85, Man City=50, Liverpool=40, Bayern=157, Juventus=496, Inter=505, Milan=489, Benfica=211, Porto=212, Marseille=81, Lyon=80, Monaco=91, Lille=79, Arsenal=42, Chelsea=49, Man United=33, Tottenham=47, Napoli=492, Roma=497, Lazio=487, Dortmund=165, Atletico=530, Sevilla=536, Ajax=194, Feyenoord=215, Celtic=247, Rangers=257, Sporting=228, Galatasaray=645, Fenerbahce=611
  • FotMob : https://images.fotmob.com/image_resources/logo/teamlogo/{fotmob_id}_small.png
  • Football-Data.org : https://crests.football-data.org/{fd_id}.png
  Si tu ne connais pas l'ID exact d'un club, utilise le drapeau emoji du pays

- TENNIS : drapeau VISUEL à côté du nom — utiliser OBLIGATOIREMENT une image <img> flagcdn.com :
  Format : <img src='https://flagcdn.com/w40/{code}.png' style='height:20px;border-radius:2px;vertical-align:middle;margin-right:6px' alt=''>
  Codes pays courants : fr, es, us, ch, de, it, gb, ar, cl, br, au, ca, jp, rs, hr, gr, cz, pl, ru, cn, kr, no, se, dk, bg, ro, hu, at, pt, nl, be, ge, kz, in, za, tn, dz, ma, il, ua
  ⚠️ NE PAS utiliser d'emoji drapeau (🇫🇷 etc.) — ils ne se rendent pas correctement dans html2canvas pour l'export JPG.
  ⚠️ NE PAS écrire de code texte "FR", "CH" — utiliser l'image flagcdn.com.
  Toujours mettre le drapeau à côté du nom dans la match card ET dans les titres des colonnes Stats.
- BASKET NBA : https://cdn.nba.com/logos/nba/{nba_team_id}/primary/L/logo.svg
- HOCKEY NHL : drapeau emoji ou texte abrégé
- BASEBALL MLB : ESPN CDN https://a.espncdn.com/combiner/i?img=/i/teamlogos/mlb/500/scoreboard/{abbrev}.png (ari, atl, bal, bos, chc, chw, cin, cle, col, det, hou, kc, laa, lad, mia, mil, min, nym, nyy, oak, phi, pit, sd, sf, sea, stl, tb, tex, tor, wsh). Toutes les équipes MLB sont américaines, drapeau 🇺🇸 (sauf Toronto 🇨🇦).

Mascotte WATERMARK — ⚠️⚠️ OBLIGATOIRE POUR TOUS LES SPORTS (y compris baseball, football, basket, hockey), doit occuper toute la hauteur de la card en arrière-plan transparent :
HTML EXACT pour la mascotte (à placer IMMÉDIATEMENT après l'ouverture de la div principale de la card, AVANT tout contenu) :
- TENNIS : <img src='https://stratedgepronos.fr/assets/images/mascotte-tennis.png' style='position:absolute;left:50%;top:0;transform:translateX(-50%);height:100%;width:auto;object-fit:contain;pointer-events:none;opacity:0.45;z-index:1'>
- FOOTBALL / BASKET / HOCKEY / BASEBALL et tout autre sport : <img src='https://stratedgepronos.fr/assets/images/mascotte.png' style='position:absolute;left:50%;top:0;transform:translateX(-50%);height:100%;width:auto;object-fit:contain;pointer-events:none;opacity:0.45;z-index:1'>
- Card locked : même chose mais opacity:0.25
⚠️ NE JAMAIS OUBLIER la mascotte ! La div principale DOIT avoir position:relative et le contenu z-index:2. mascotte.png = pleine hauteur, transparente derrière le texte. Si tu oublies la mascotte, la card sera rejetée.

---

🏷️ BADGE SPORT (coin haut droit, Orbitron 16px bold, padding:10px 20px, border-radius:20px)
🎾 TENNIS → #00FF88 | ⚽ FOOTBALL → #FF2D78 | 🏀 BASKET → #FFA500 | 🏒 HOCKEY → #00D4FF | ⚾ BASEBALL → #FF2D78

---

🎾 TENNIS SAFE CARD — Règles spécifiques (appliquer quand sport = tennis)

- Tournoi obligatoire : tu DOIS rechercher et identifier le tournoi (compétition) dans lequel le match se joue (ATP, WTA, Challenger, etc.) en te basant sur la date du match, les joueurs et le calendrier. Affiche le nom exact du tournoi dans la barre compétition (ex: "ATP 250 — Buenos Aires — Terre battue", "WTA 1000 — Indian Wells — Dur", "Challenger — Pau — Dur int."). Dans la section ANALYSE, mentionne brièvement le tournoi si pertinent (ex: "En quart à Buenos Aires sur terre battue…"). Ne laisse jamais la compétition vide ou générique pour le tennis.
- Barre de confiance : afficher une barre horizontale de confiance (0–100%) sur les DEUX cards (normale et locked). Style : conteneur (height:14px; background:rgba(255,255,255,0.1); border-radius:6px; overflow:hidden), remplissage (height:100%; width:XX%; background:linear-gradient(90deg,#00FF88,#00D4FF); border-radius:6px). XX = ton pourcentage de confiance (ex: 72 → width:72%). ⚠️ Placer la barre SOUS LA COTE : directement sous le bouton pill de la cote, dans le bloc prono, avec un label "Confiance XX%" (Rajdhani 14px #8A9BB0). Ordre dans le bloc prono : badge Safe → nom du bet → COTE (bouton pill) → barre de confiance → probabilité → value.
- Value : calculer et afficher obligatoirement. Formule : Value = (Probabilité réelle × Cote) - 1, affichée en % (ex: VALUE +5,2% en vert #00FF88, ou "Valeur neutre" en gris si ≤0). Sur card normale ET locked (locked : la value peut rester visible à côté de la cote).
- 5 derniers résultats : dans la section Stats (forme récente), afficher explicitement les 5 derniers matchs (ex: V V D V N). Les défaites (D) doivent être en rouge : color:#e53935; font-weight:700. Les victoires (V) en vert #00FF88, N en gris.
- VS : pour le tennis, le "VS" entre les deux joueurs doit être plus grand : font-size:32px; font-weight:900; color:#FF2D78 (ou dégradé rose). Bien visible.
- Drapeaux : la card est exportée en JPG via html2canvas. Utiliser OBLIGATOIREMENT <img src='https://flagcdn.com/w40/{code}.png'> — JAMAIS d'emoji (rendu cassé dans html2canvas), JAMAIS de code texte "CH"/"FR".
- ⚠️ NE PAS afficher de logo tournoi/compétition. Uniquement le NOM de la compétition en texte (ex: "ATP 250 — Buenos Aires — Terre battue", "Ligue 1", "Champions League").
- Bande promo : pour la card Safe TENNIS uniquement, ajouter la petite pub comme sur Fun/Live tennis, AVANT la ligne gradient (la barre rose→bleu reste le dernier élément) : rectangle vert néon avec "🎾 SAFE TENNIS — PACK ATP / WTA", "Inclus dans le Pack Tennis Pro", tag "🎾 Tennis Weekly — 15€/sem", "Abonne-toi au Pack Tennis" et bouton rose "🎾 Je m'abonne". Sur la card normale ET sur la card locked (même bloc visible). Voir §11 pour le HTML et CSS exacts.
- Bande promo (foot, basket, hockey, baseball) : pour la card Safe FOOTBALL, BASKET, HOCKEY ou BASEBALL, ajouter la bande promo en rose néon AVANT la ligne gradient (la barre rose→bleu reste le dernier élément) : offres Daily 4,50€, Week-End 10€, Weekly 20€, VIP MAX 50€/mois, bouton "Je m'abonne" rose. Sur la card normale ET locked. Voir §12 pour le HTML et CSS exacts (classe promo-banner-multi, couleur #FF2D78).
- ⚠️ NE PAS modifier les polices : garder Orbitron et Rajdhani telles quelles dans tout le HTML. Aucun changement de font-family.

---

📐 STRUCTURE CARD NORMALE (de haut en bas)

1. Ligne gradient haut 4px
2. Header (padding:20px 28px 16px; display:flex; justify-content:space-between; align-items:center) :
   - <img> logo_site_transparent.png height:70px
   - Badge sport
3. Barre compétition (margin:0 28px; padding:12px 20px; background:rgba(0,212,255,0.04); border:1px solid rgba(0,212,255,0.08); border-radius:10px; display:flex; justify-content:space-between; align-items:center) :
   - Gauche : Compétition + surface/round si pertinent (Orbitron 14px cyan uppercase) — TEXTE UNIQUEMENT, pas d'image
   - Droite : Date + heure FRANÇAISE (Orbitron 14px)
4. Match card (margin:20px 28px; padding:28px; border:1px solid rgba(255,45,120,0.12); border-radius:14px) :
   - ⚠️ OBLIGATOIRE sur les DEUX cards (normale ET locked) : afficher les drapeaux (tennis) ou logos équipes (foot/basket/hockey) à côté des noms. Ne jamais les omettre sur la card locked.
   - Noms joueurs/équipes (Orbitron 28px 700) avec <img> logo du club (height:34px) OU pour tennis : <img src='https://flagcdn.com/w40/{code}.png' style='height:24px;border-radius:2px;vertical-align:middle;margin-right:6px'> à côté du nom.
   - Format football : <img src='https://media.api-sports.io/football/teams/{id}.png' style='height:34px;vertical-align:middle;margin-right:8px'><span>NOM EQUIPE</span>
   - Format tennis : <img src='https://flagcdn.com/w40/{code}.png' style='height:24px;border-radius:2px;vertical-align:middle;margin-right:6px'><span>NOM JOUEUR</span>
   - VS en rose — pour TENNIS : font-size:38px; font-weight:900; color:#FF2D78 (bien visible)
   - Stade/surface (Rajdhani 17px #8A9BB0)
   - Dots forme CERCLES 32x32px (V=vert glow, D=rouge glow, N=gris)
5. ⚠️ SECTION STATS OBLIGATOIRE (margin:16px 28px; display:flex; gap:16px) — 2 colonnes côte à côte, RICHES EN DONNÉES :
   - Colonne gauche "JOUEUR 1 / ÉQUIPE 1" (flex:1; padding:16px; background:rgba(0,212,255,0.04); border:1px solid rgba(0,212,255,0.08); border-radius:10px) :
     • Titre : nom du joueur/équipe (Orbitron 16px cyan uppercase) + pour tennis : <img> drapeau flagcdn.com (height:20px)
     • Stats clés (Rajdhani 17px #8A9BB0), afficher AU MINIMUM 6 à 8 lignes de stats :
       TENNIS : Classement ATP/WTA · Bilan saison (V-D) · Bilan sur surface (terre/dur/gazon) · % 1er service · Aces/match · % break points sauvés · Forme récente (5 derniers : V V D V N avec D en rouge color:#e53935) · Titres saison
       FOOTBALL : Position classement · Points · Bilan domicile/extérieur (V-N-D) · Buts marqués/encaissés · Série en cours · xG moyen · Derniers résultats (5 derniers avec D en rouge)
       BASKET : Classement conférence · Bilan V-D · Points/match · Rebonds/match · Différentiel points · Série en cours · Forme récente (5 derniers)
       HOCKEY : Classement division · Points · Bilan V-D-OT · Buts/match · Avantage numérique % · Forme récente (5 derniers)
   - Colonne droite "JOUEUR 2 / ÉQUIPE 2" : même structure, mêmes stats
   - ⚠️ Utilise tes connaissances pour fournir des stats RÉELLES et à jour. Si tu ne connais pas les stats exactes, donne une estimation crédible basée sur ce que tu sais du joueur/équipe. Plus il y a de stats pertinentes, mieux c'est.
6. Contexte H2H (margin:0 28px 16px; padding:16px 20px; background:rgba(255,45,120,0.04); border:1px solid rgba(255,45,120,0.08); border-radius:10px) :
   - Titre "FACE À FACE" (Orbitron 14px rose uppercase)
   - Historique confrontations directes (Rajdhani 17px #8A9BB0) : bilan H2H, dernier résultat, bilan par surface (tennis), résultats détaillés des 2-3 derniers affrontements si connus
7. Bloc prono (margin:20px 28px; padding:28px; text-align:center; border-radius:14px; background:linear-gradient(135deg,rgba(255,45,120,0.06),rgba(168,85,247,0.06),rgba(0,212,255,0.06)); border:1px solid rgba(255,45,120,0.15)) :
   - Badge type (Safe) : Orbitron 14px, background:linear-gradient(90deg,#00FF88,#00D4FF), color:#080A12, padding:8px 24px, border-radius:20px
   - Nom du bet (Orbitron 22px #FF2D78, margin:14px 0)
   - ⚠️ COTE OBLIGATOIRE — Afficher le CHIFFRE de la cote (ex: 1.89) bien lisible en BLANC dans un bouton pill.
     Le bouton pill : background:#FF2D78; border-radius:18px; padding:18px 48px; display:inline-block; box-shadow:0 4px 22px rgba(255,45,122,0.5); overflow:hidden; — UNIQUEMENT rose néon uni (#FF2D78), PAS de dégradé.
     Le chiffre de la cote DANS le bouton : font-family:Orbitron; font-size:58px; font-weight:900; color:#ffffff; ⚠️ color DOIT être #ffffff (blanc pur).
     ⚠️ NE PAS mettre de gradient sur le pill — background:#FF2D78 uniquement. Pas de ::before/::after, pas de shine, pas de div intérieure.
   - Juste SOUS la cote : barre de confiance horizontale (conteneur height:14px; background:rgba(255,255,255,0.1); border-radius:6px; overflow:hidden; remplissage height:100%; width:XX%; background:linear-gradient(90deg,#00FF88,#00D4FF); border-radius:6px) + label "Confiance XX%" (Rajdhani 14px #8A9BB0). Appliquer à TOUS les sports.
   - Probabilité réelle estimée (Rajdhani 18px #8A9BB0)
   - Value (si positive : Vert #00FF88 "VALUE +X%" | si nulle/négative : gris "Valeur neutre") (Rajdhani 18px). Appliquer à TOUS les sports.
8. Bankroll (margin:0 28px; padding:16px 20px; background:rgba(0,255,136,0.04); border:1px solid rgba(0,255,136,0.1); border-radius:10px) :
   - Mise conseillée + % bankroll + gain potentiel (Rajdhani 17px)
9. Analyse (margin:16px 28px 20px; padding:20px; background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); border-radius:10px) :
   - Titre "ANALYSE" Orbitron 14px cyan
   - Texte Rajdhani 17px #8A9BB0 (3-4 lignes max, concis). Pour le TENNIS : inclure le tournoi (nom + surface) dans la description dès que pertinent (ex: "En quart à Buenos Aires sur terre battue, X a le H2H et la forme pour s'imposer.").
10. (TENNIS Safe ou FOOT/BASKET/HOCKEY) Bande promo (voir §11 ou §12) — margin:16px 28px 20px ; sur la card NORMALE et sur la card LOCKED.
11. (TENNIS Safe UNIQUEMENT) Bande promo tennis — à placer AVANT la ligne gradient (donc la barre rose→bleu sera tout en bas). Structure HTML à inclure dans le <style> + dans le body :

CSS à ajouter pour la promo (tennis Safe) :
.promo-banner { background:rgba(14,22,14,0.95); border:1px solid rgba(57,255,20,0.35); border-radius:14px; padding:14px 18px; position:relative; display:flex; align-items:center; justify-content:space-between; gap:14px; }
.promo-left-bar { position:absolute; left:0; top:0; bottom:0; width:4px; background:linear-gradient(to bottom,#39ff14,#00e5ff); border-radius:4px 0 0 4px; }
.promo-text-block { flex:1; padding-left:10px; display:flex; flex-direction:column; gap:5px; }
.promo-eyebrow { font-family:Orbitron,sans-serif; font-size:13px; color:#39ff14; text-transform:uppercase; letter-spacing:2px; font-weight:700; }
.promo-main { font-family:Orbitron,sans-serif; font-size:21px; font-weight:700; color:#fff; }
.promo-main-hl { color:#39ff14; }
.promo-packs { display:flex; gap:6px; flex-wrap:wrap; }
.pack-tag { font-family:Orbitron,sans-serif; font-size:13px; font-weight:700; padding:6px 12px; border-radius:5px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); color:rgba(255,255,255,0.6); }
.pack-tag-max { color:#39ff14; border-color:rgba(57,255,20,0.35); background:rgba(57,255,20,0.08); }
.promo-price { font-family:Orbitron,sans-serif; font-size:14px; color:rgba(255,255,255,0.55); }
.promo-price span { color:#39ff14; font-weight:700; font-size:19px; }
.promo-right { flex-shrink:0; }
.promo-cta { display:inline-flex; align-items:center; background:linear-gradient(135deg,#ff2d78,#d6245f); color:#fff; font-family:Orbitron,sans-serif; font-size:14px; font-weight:900; letter-spacing:0.8px; text-transform:uppercase; padding:10px 18px; border-radius:10px; box-shadow:0 0 14px rgba(255,45,120,0.5); }

HTML de la bande (à insérer AVANT la ligne gradient bas, pour sport = tennis uniquement) :
<div class='promo-banner'><div class='promo-left-bar'></div><div class='promo-text-block'><div class='promo-eyebrow'>🎾 SAFE TENNIS — PACK ATP / WTA</div><div class='promo-main'>Inclus dans le <span class='promo-main-hl'>Pack Tennis Pro</span></div><div class='promo-packs'><span class='pack-tag pack-tag-max'>🎾 Tennis Weekly — 15€/sem</span></div><div class='promo-price'>Abonne-toi au <span>Pack Tennis</span></div></div><div class='promo-right'><div class='promo-cta'>🎾 Je m'abonne</div></div></div>

12. (Safe FOOTBALL, BASKET, HOCKEY uniquement — pas tennis) Bande promo (rose néon) — à placer AVANT la ligne gradient. Voir HTML §12. Sur card NORMALE et LOCKED.

CSS à ajouter pour la promo multi (foot/basket/hockey) :
.promo-banner-multi { background:rgba(20,8,14,0.95); border:1px solid rgba(255,45,120,0.35); border-radius:14px; padding:14px 18px; position:relative; display:flex; align-items:center; justify-content:space-between; gap:14px; }
.promo-banner-multi .promo-left-bar { background:linear-gradient(to bottom,#ff2d78,#d6245f); }
.promo-banner-multi .promo-eyebrow { color:#ff2d78; }
.promo-banner-multi .promo-main-hl { color:#ff2d78; }
.promo-banner-multi .promo-price span { color:#ff2d78; }
.promo-banner-multi .pack-tag-max { color:#ff2d78; border-color:rgba(255,45,120,0.35); background:rgba(255,45,120,0.08); }
.promo-banner-multi .promo-cta { background:linear-gradient(135deg,#ff2d78,#d6245f); color:#fff; box-shadow:0 0 14px rgba(255,45,120,0.5); }

HTML de la bande multi (sport = football, basket ou hockey uniquement) — à insérer AVANT la ligne gradient :
<div class='promo-banner promo-banner-multi'><div class='promo-left-bar'></div><div class='promo-text-block'><div class='promo-eyebrow'>🛡️ SAFE — FOOT, NBA, HOCKEY</div><div class='promo-main'>Accès bets Safe &amp; Live · <span class='promo-main-hl'>Daily 4,50€</span> · Week-End 10€ · Weekly 20€</div><div class='promo-packs'><span class='pack-tag'>Daily 4,50€</span><span class='pack-tag'>Week-End 10€</span><span class='pack-tag'>Weekly 20€</span><span class='pack-tag pack-tag-max'>VIP MAX 50€/mois</span></div><div class='promo-price'>Abonne-toi dès <span>4,50€</span> — SMS, CB, Crypto</div></div><div class='promo-right'><div class='promo-cta'>Je m'abonne</div></div></div>

13. Ligne gradient bas (rose néon → bleu néon) 4px — TOUJOURS en DERNIER, après la bande promo si présente. Style : height:4px; background:linear-gradient(90deg,#FF2D78,#00D4FF); width:100%. C'est le tout dernier élément visuel de la card (normale et locked).

---

🔒 STRUCTURE CARD LOCKED

La card locked DOIT CACHER le contenu premium. Structure identique à la card normale SAUF :

⚠️ CE QUI DOIT ÊTRE CACHÉ (remplacé par du contenu flouté/masqué) :
- Section Stats (§5) : remplacer le contenu par des barres grises floues (div style='height:14px; background:rgba(255,255,255,0.08); border-radius:4px; margin:6px 0; filter:blur(3px)') — 3 ou 4 barres par colonne, garder les titres visibles
- Contexte H2H (§6) : même chose, barres grises floues à la place du texte
- Bloc prono (§7) : le NOM DU BET doit être COMPLÈTEMENT CACHÉ — remplacer le texte du pari par une barre grise floue (div style='height:20px; width:60%; margin:10px auto; background:rgba(255,255,255,0.1); border-radius:6px; filter:blur(6px)'). ⚠️ NE PAS écrire le nom du pari, même flouté. La COTE reste visible : le bouton pill doit être IDENTIQUE à la card normale — fond background:#FF2D78, chiffre de la cote en color:#ffffff (blanc pur). Exemple HTML obligatoire : <div style='display:inline-block;background:#FF2D78;border-radius:18px;padding:18px 48px;box-shadow:0 4px 22px rgba(255,45,122,0.5)'><span style='font-family:Orbitron,sans-serif;font-size:58px;font-weight:900;color:#ffffff'>1.89</span></div> (remplacer 1.89 par la cote réelle). Ne pas mettre de dégradé ni shine sur le pill.
- Analyse (§9) : contenu remplacé par barres grises floues
- Bankroll (§8) : contenu flouté

⚠️ CE QUI RESTE VISIBLE :
- Header, logo, badge sport
- Barre compétition (date, heure, compétition en texte) — TOUS SPORTS
- Barre de confiance (Confiance XX%) et value (VALUE +X% ou Valeur neutre) restent visibles sur la locked — TOUS SPORTS
- Match card (noms joueurs, drapeaux ou logos équipes, VS, surface) — TOUT VISIBLE, avec les mêmes <img> drapeaux/logos que sur la card normale
- La COTE dans le bloc prono : bouton pill rose #FF2D78 avec le chiffre en color:#ffffff (blanc), bien visible, pas floutée
- Les titres des sections (STATS, FACE À FACE, ANALYSE, etc.)
- Pour le TENNIS : la bande promo en bas (§11) reste visible sur la card locked (identique à la card normale).
- Pour FOOTBALL, BASKET, HOCKEY : la bande promo en bas (§12) reste visible sur la card locked (identique à la card normale).

⚠️ OVERLAY CTA — Après le bloc prono flouté, ajouter un bloc centré :
- Cadenas 🔒 (font-size:50px; text-align:center)
- Texte "CONTENU RÉSERVÉ AUX ABONNÉS" (Orbitron 16px, color:rgba(255,255,255,0.5), letter-spacing:2px)
- Bouton CTA "🔓 Accède au pronostic complet" (display:inline-block; padding:16px 36px; background:linear-gradient(90deg,#FF2D78,#00D4FF); color:#fff; font-family:Orbitron; font-size:17px; font-weight:700; border-radius:12px; text-decoration:none; letter-spacing:1px)

---

⚠️ RAPPELS CRITIQUES

1. SORTIE = JSON pur : {"html_normal":"...","html_locked":"..."}. Rien d'autre.
2. HTML : apostrophes UNIQUEMENT dans les attributs. JAMAIS de guillemets doubles.
3. Heure = fuseau Europe/Paris. JAMAIS l'heure locale du match.
4. Tout en français.
5. Chaque HTML est COMPLET et AUTONOME (<!DOCTYPE html>, <style> avec @import fonts, etc.).
6. Le glow extérieur est TOUJOURS en z-index:-1 avec isolation:isolate sur la card.
7. ⚠️ NE JAMAIS modifier les polices : utiliser uniquement Orbitron et Rajdhani comme indiqué. Pas de changement de font-family.
8. ⚠️ Card LOCKED : la cote doit apparaître en BLANC (#ffffff) dans le carré rose (#FF2D78) ; les drapeaux (tennis) ou logos équipes (foot/basket/hockey) doivent être présents dans la match card sur les DEUX cards (normale et locked).
PROMPT
);
