<?php
// ============================================================
// STRATEDGE — claude-config.php V9
// V9 : CLAUDE_FUN_ENRICH_PROMPT ajouté
//      Live + Fun = enrichissement JSON uniquement (template PHP)
//      Safe = Claude génère le HTML complet (inchangé)
// ⚠️  NE JAMAIS exposer ce fichier publiquement
// ============================================================

if (!defined('ABSPATH')) { define('ABSPATH', true); }

define('CLAUDE_API_KEY', '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');

define('CLAUDE_MODEL', 'claude-sonnet-4-6');

define('CLAUDE_THINKING_ENABLED', false);

// ============================================================
// ⚡ LIVE — Enrichissement uniquement (JSON, pas de HTML)
// ============================================================
define('CLAUDE_LIVE_ENRICH_PROMPT', <<<'PROMPT'
Tu reçois les infos d'un match (sport, match, pronostic, cote) et une date/heure fournies. Tu réponds UNIQUEMENT par un objet JSON valide, sans aucun texte avant ou après, sans backticks.
Clés obligatoires :
- date_fr : recopie EXACTEMENT la valeur "date_fr" donnée dans le message (date du match).
- time_fr : recopie EXACTEMENT la valeur "time_fr" donnée dans le message (heure du match).
- player1 : nom du premier joueur/équipe (ex: "Garin C.")
- player2 : nom du second (ex: "Baez S.")
- flag1 : emoji drapeau pays du joueur 1 (ex: "🇨🇱")
- flag2 : emoji drapeau pays du joueur 2 (ex: "🇦🇷")
- competition : compétition + surface si pertinent (ex: "ATP 250 - Buenos Aires - Terre battue")
- prono_joueur : 1 ou 2 selon le pronostic. Si le pronostic indique que le joueur 1 gagne (ou équipe 1), mets 1. Si le joueur 2 gagne, mets 2. Sinon 1.
Ne modifie jamais date_fr ni time_fr : utilise uniquement les valeurs fournies dans le message.
Exemple : {"date_fr":"Mercredi 26 Février 2026","time_fr":"15:30","player1":"Garin C.","player2":"Baez S.","flag1":"🇨🇱","flag2":"🇦🇷","competition":"ATP 250 - Buenos Aires - Terre battue","prono_joueur":1}
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
      "flag1": "🇧🇪",
      "flag2": "🇭🇷",
      "prono": "Les 2 équipes marquent + +4.5 corners Genk",
      "cote": "2.95"
    },
    {
      "match": "Celta Vigo vs PAOK",
      "flag1": "🇪🇸",
      "flag2": "🇬🇷",
      "prono": "2ème MT +0.5 Celta + +4.5 corners Celta",
      "cote": "2.49"
    }
  ],
  "cote_totale": "7.35",
  "confidence": 68
}

Règles :
- date_fr = date du PREMIER match (le plus tôt), en toutes lettres en français
- time_fr = heure du PREMIER match, fuseau Europe/Paris obligatoire
- bets = tableau ordonné de tous les paris. Chaque entrée : match (équipes), flag1 (emoji drapeau équipe/pays 1), flag2 (emoji drapeau équipe/pays 2), prono (texte du pari tel que fourni), cote (valeur exacte fournie)
- cote_totale = produit de toutes les cotes individuelles, arrondi à 2 décimales
- confidence = indice de confiance global estimé entre 40 et 85 (basé sur le nombre de sélections et la hauteur des cotes : moins de sélections + cotes modérées = confiance plus haute)
- Les drapeaux : tu connais les nationalités/pays des équipes. Si incertain, utilise le drapeau du pays le plus probable.
- Heure = TOUJOURS fuseau Europe/Paris, jamais l'heure locale du match.
- JSON pur uniquement. Aucun texte, aucun commentaire, aucun backtick.
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

Dimensions : width:1080px; overflow:hidden
⚠️ PAS de min-height fixe. Hauteur auto, contenu compact, pas de grands vides.
Les 2 cards (normale et locked) doivent avoir la MÊME largeur (1080px) et des proportions similaires.
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

- TENNIS : drapeau emoji du pays à côté du nom (ex: 🇪🇸 MASAROVA, 🇺🇸 OSUIGWE)
- BASKET NBA : https://cdn.nba.com/logos/nba/{nba_team_id}/primary/L/logo.svg
- HOCKEY NHL : drapeau emoji ou texte abrégé

Mascotte (position:absolute; left:50%; transform:translateX(-50%); top:0; height:100%; object-fit:contain; pointer-events:none) :
- TENNIS → src='https://stratedgepronos.fr/assets/images/mascotte-tennis.png'
- Autres sports → src='https://stratedgepronos.fr/assets/images/mascotte-rose.png'
- Card normale : opacity:0.14; z-index:1
- Card locked : opacity:0.28; z-index:1

---

🏷️ BADGE SPORT (coin haut droit, Orbitron 13px bold, padding:8px 18px, border-radius:20px)
🎾 TENNIS → #00FF88 | ⚽ FOOTBALL → #FF2D78 | 🏀 BASKET → #FFA500 | 🏒 HOCKEY → #00D4FF

---

📐 STRUCTURE CARD NORMALE (de haut en bas)

1. Ligne gradient haut 4px
2. Header (padding:20px 28px 16px; display:flex; justify-content:space-between; align-items:center) :
   - <img> logo_site_transparent.png height:70px
   - Badge sport
3. Barre compétition (margin:0 28px; padding:12px 20px; background:rgba(0,212,255,0.04); border:1px solid rgba(0,212,255,0.08); border-radius:10px; display:flex; justify-content:space-between) :
   - Compétition (Orbitron 11px cyan uppercase)
   - Date + heure FRANÇAISE
4. Match card (margin:20px 28px; padding:28px; border:1px solid rgba(255,45,120,0.12); border-radius:14px) :
   - Noms joueurs/équipes (Orbitron 24px 700) avec <img> logo du club (height:30px) à côté du nom OU drapeau emoji si pas de logo
   - Format : <img src='...' style='height:30px;vertical-align:middle;margin-right:8px'><span>NOM EQUIPE</span>
   - VS en rose
   - Stade/surface (14px #8A9BB0)
   - Dots forme CERCLES 30x30px (V=vert glow, D=rouge glow, N=gris)
5. Contexte (flex; gap:16px; margin:16px 28px) : 2 boxes, titre cyan + texte #8A9BB0
6. Bloc prono (margin:20px 28px; padding:28px; text-align:center; border-radius:14px; background:linear-gradient(135deg,rgba(255,45,120,0.06),rgba(168,85,247,0.06),rgba(0,212,255,0.06)); border:1px solid rgba(255,45,120,0.15)) :
   - Badge type (Safe) : Orbitron 12px, background:linear-gradient(90deg,#00FF88,#00D4FF), color:#080A12, padding:6px 20px, border-radius:20px
   - Nom du bet (Orbitron 18px #FF2D78, margin:14px 0)
   - ⚠️ COTE OBLIGATOIRE — C'est l'élément central de la card. TOUJOURS l'afficher :
     Cote (Orbitron 64px 900 #FF2D78, glow text-shadow, margin:10px 0)
   - Probabilité réelle estimée (Rajdhani 16px #8A9BB0)
   - Value (si positive : Vert #00FF88 "VALUE +X%" | si nulle/négative : gris "Valeur neutre")
7. Bankroll (margin:0 28px; padding:16px 20px; background:rgba(0,255,136,0.04); border:1px solid rgba(0,255,136,0.1); border-radius:10px) :
   - Mise conseillée + % bankroll + gain potentiel (Rajdhani 15px)
8. Analyse (margin:16px 28px 20px; padding:20px; background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); border-radius:10px) :
   - Titre "ANALYSE" Orbitron 11px cyan
   - Texte Rajdhani 15px #8A9BB0 (3-4 lignes max, concis)
9. Ligne gradient bas 4px

---

🔒 STRUCTURE CARD LOCKED

Même design Safe mais :
- Analyse masquée (blocs gris)
- Pronostic et cote visibles (défloutés)
- Overlay blur sur analyse : filter:blur(4px); pointer-events:none
- Bouton CTA "🔓 Accède au pronostic complet" background:linear-gradient(90deg,#FF2D78,#00D4FF)
- Cadenas 🔒 50px centré au-dessus du CTA

---

⚠️ RAPPELS CRITIQUES

1. SORTIE = JSON pur : {"html_normal":"...","html_locked":"..."}. Rien d'autre.
2. HTML : apostrophes UNIQUEMENT dans les attributs. JAMAIS de guillemets doubles.
3. Heure = fuseau Europe/Paris. JAMAIS l'heure locale du match.
4. Tout en français.
5. Chaque HTML est COMPLET et AUTONOME (<!DOCTYPE html>, <style> avec @import fonts, etc.).
6. Le glow extérieur est TOUJOURS en z-index:-1 avec isolation:isolate sur la card.
PROMPT
);
