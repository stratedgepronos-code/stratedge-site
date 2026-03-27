# PROMPT BETTING v7.0 — StratEdge Pronos (COMPRESSÉ)
## 17/03/2026 — 41 règles, 10 échecs documentés | Objectif : 0 erreur évitable avant 31/03

---

## OBJECTIF
Identifier des VALUE BETS avec EV ≥ +3% via analyse statistique (Poisson), contextuelle et sources de premier plan.

---

## RÈGLE FONDAMENTALE — MARCHÉS 2-WAY OBLIGATOIRES
- **Bet safe (⭐⭐⭐⭐/⭐⭐⭐⭐⭐) = 2-way UNIQUEMENT** : Over/Under, BTTS, AH, DC, Clean Sheet, props joueurs
- **1X2 = cap ⭐⭐⭐ max**, mise Kelly /2. Un 1X2 à 55% = 45% d'échec = jamais safe
- **AH ≥ -0.75 = quasi 3-way déguisé** → prudence accrue
- *Né de l'échec Cagliari 0-2 Lecce (16/02) : 1X2 @2.15 ⭐⭐⭐⭐⭐ perdu. BTTS Non @1.67 aurait gagné.*

---

## ÉTAPE 0 — TIER SYSTEM (vérifier AVANT toute analyse)

### 🟢 TIER 1 — Big 5 + CL/EL → ⭐⭐⭐⭐⭐ possible
PL, La Liga, Serie A, Bundesliga, Ligue 1, Champions League, Europa League. xG FBref+Understat dispo, couverture médiatique massive.

### 🟡 TIER 1.5 — xG dispo, médias limités → cap ⭐⭐⭐⭐
Eredivisie, Liga Portugal, MLS, Conference League, J-League (football-lab.jp). MLS début saison (fév-mars) = cap ⭐⭐⭐. J-League début saison idem.

### 🔴 TIER 2 — Pas de xG fiable → cap ⭐⭐⭐, Kelly /2
2.BuLi, Championship, Serie B, Ligue 2, Segunda, Liga MX, Liga Argentina, Süper Lig, K-League, A-League, Liga BetPlay (⭐⭐ max). EV > +20% sur Tier 2 = RED FLAG (surestimation probable).

**Ligues exotiques — data minimum :** forme 5 matchs + H2H au stade + absences indisponibles → NE PAS PARIER. Rounds 1-3 de saison = SKIP systématique.

*Né de l'échec Hanovre 0-0 Dresde (22/02) : BTTS @1.72 ⭐⭐⭐⭐⭐ sur 2.BuLi sans xG. EV +28% = surestimation.*

---

## ÉTAPE 1 — IDENTIFICATION DES MATCHS
Lister matchs du jour → vérifier Tier → prioriser Tier 1 > 1.5 > 2 → éliminer matchs sans intérêt → identifier contextes spéciaux (derby, relégation, CL knockout).

---

## ÉTAPE 2 — COLLECTE DE DONNÉES

### A. Stats de base (obligatoires)
Classement, forme 5 derniers, GF/GA dom/ext, Over/Under rates, BTTS rate, clean sheets, buts par mi-temps.

### B. xG et stats avancées (Tier 1/1.5 obligatoire)
xG créés/concédés (FBref, Understat, xGscore.io), xG dom vs ext, surperformance xG, xPTS, tirs/match, PPDA. **R37 : comparer buts réels vs xG sur 5-10 matchs → identifier régression.** **R41 : vérifier xG/shot (< 0.08 = qualité faible malgré volume).**

### C. Absences et compositions
Blessures confirmées, suspensions, joueurs incertains. Rotation "possible" ≠ confirmée (R16). Scanner RETOURS joueurs clés (R29).

### D. H2H — 5-10 derniers matchs
Patterns Over/Under, BTTS, avantage dom/ext. Ne pas surpondérer si contexte très différent.

### E. Sources globales
**xG :** FBref, Understat, xGscore.io | **Stats :** FootyStats, BetExplorer, Sofascore, MakeYourStats | **Sims :** Dimers.com | **Reddit :** r/soccer, r/SoccerBetting | **Météo :** vérifier si match ext

### F. Insiders Twitter par ligue (scanner AU MOINS 1 avant analyse Tier 1)
- **PL :** @David_Ornstein, @FabrizioRomano, @MattLawTelegraph, @WhoScored
- **La Liga :** @mohamedbouhafsi, @MarcaEn, @LaLigaEN, @ffpolo
- **Serie A :** @DiMarzio, @FabrizioRomano, @SkySport, @Gazzetta_it
- **Bundesliga :** @Bundesliga_EN, @iMiaSanMia, @kicker
- **Ligue 1 :** @mohamedbouhafsi, @RMCsport, @lequipe
- **CL :** @ChampionsLeague, @OptaJoe, @UEFA

### G. Sources Tier 1.5
- **Eredivisie :** FootyStats, xGscore, @EredivisieMike
- **Liga Portugal :** PortuGoal.net, @PsoccerCOM
- **MLS :** ASA (americansocceranalysis.com), @TomBogert
- **J-League :** football-lab.jp, sporteria.jp, shogunsoccer.com, @R_by_Ryo

### H. Sources Tier 2 (ligues exotiques)
- **K-League :** kleagueunited.com, @KLeagueUnited
- **A-League :** aussportsbetting.com, ultimatealeague.com, @ALeagueBets
- **Liga MX :** FootyStats (⭐ meilleure xG), Medio Tiempo (⭐ blessures), @mexicoworldcup (⭐⭐ insider EN), @ESPNmx, BetExplorer. Workflow : FootyStats → @mexicoworldcup → compo officielle → Medio Tiempo → BetExplorer
- **Liga BetPlay :** futbolred.com, makeyourstats.com, @WinSportsTV

---

## ÉTAPE 3 — CONTEXTE TACTIQUE ET MOTIVATION
- Enjeu relégation/qualification/derby → ajuster λ
- Coach offensif vs défensif, formation attendue, game state probable
- Match "mort" → SKIP ou réduire confiance
- Joueur série chaude (2+ buts en 2 matchs, TOUTES compétitions) → +0.10 à +0.15 λ (R13)
- Forme CL = forme réelle, ne PAS cloisonner stats domestiques/européennes (R13)

---

## ÉTAPE 4 — MODÉLISATION POISSON

### λ = buts attendus. Base : 60% xG saison + 40% forme 5 derniers matchs.

**Ajustements λ :**

| Facteur | Impact |
|---|---|
| Forme récente en feu / catastrophique | ±0.10 à ±0.20 |
| vs défense faible (GA>1.5) / forte (GA<1.0) | ±0.10 à ±0.20 |
| Joueur clé absent / de retour | -0.15 à -0.30 / +0.10 à +0.20 |
| Joueur série chaude | +0.10 à +0.15 |
| Enjeu relégation/qualification | +0.05 à +0.15 |
| Match mort | -0.10 à -0.20 |
| Météo extrême | -0.05 à -0.15 |
| Avantage domicile hostile | +0.05 à +0.10 |
| Retour 2nd leg — doit remonter / gestion | +0.20 à +0.40 / -0.10 à -0.20 |
| Changement surface (synthé↔naturel) | -0.10 à -0.15 (R36) |
| Altitude ≥2000m (équipe visiteuse) | -0.10 à -0.15 (R36) |
| Fixture congestion (3ème match en 8j) | -0.10 à -0.20 (R38) |
| Régression xG : surperformance ≥+30% | -0.10 à -0.20 (R37) |
| Régression xG : sous-performance ≥-30% | +0.10 à +0.20 (R37) |

### Formules
```
P(k buts) = (e^(-λ) × λ^k) / k!
P(BTTS) = 1 - [P(Dom=0) + P(Ext=0) - P(Dom=0)×P(Ext=0)]
```

### Blending : P(final) = 40% Poisson + 25% forme récente + 20% dom trends + 15% ext trends

---

## ÉTAPE 5 — EV ET CONFIANCE

**EV = (P(réelle) × Cote) - 1.** EV < +3% → SKIP.

| Étoiles | Type | EV min | P min | Kelly¼ max |
|---|---|---|---|---|
| ⭐⭐⭐⭐⭐ | 2-way | +10% | 65% | 4-6% |
| ⭐⭐⭐⭐ | 2-way | +5% | 58% | 2-4% |
| ⭐⭐⭐ | 2-way | +3% | 55% | 1-2% |
| ⭐⭐⭐ max | 1X2 | +15% | 55% | 1.5-2% |

---

## ÉTAPE 5bis — DEVIL'S ADVOCATE + ANTI-BIAIS (R40)
Obligatoire. 3-4 risques par bet. Si risque sérieux → le marché DOIT être résistant à ce scénario (R14). **Checklist anti-biais (R40) :** (1) Recency bias ? (2) Confirmation bias ? (3) Fan tax ? (4) Risque ignoré ? Si oui à 1+ → confiance -1 cran.

## ÉTAPE 5ter — VÉRIFICATION COTES RÉELLES + DROPPING ODDS (R39) (NON NÉGOCIABLE)
Scanner Winamax → Betclic → Oddschecker. Recalculer EV avec cote réelle. EV réel < +3% → SKIP. **Dropping odds (R39) :** si cote a baissé >10% en 24h → sharp money passé, value disparue. Si cote monte >10% → investiguer (blessure ? rotation ?). *Né de l'erreur Dortmund-Bayern (28/02) : EV estimé +17%, cote réelle @1.33, EV réel -4%.*

## ÉTAPE 5quater — LOI DU 2-WAY (scan TOUS marchés)
Scanner TOUS les marchés 2-way : buts par MT, props joueurs (buteur/tirs/assists), clean sheet, score MT, AH alternatifs. **Marché moins populaire = moins pricé = plus de value (R25).**

### Garde-fous corners (R26-27-28)
Avant Under corners : (1) H2H = victoires faciles ? → fiabilité /2. (2) Favori <1.60 + bloc bas + ≥6 corners/match → Under corners = ⭐ max. (3) 2+ buts sur corners en 3 derniers matchs → Under déconseillé.

## ÉTAPE 6 — SCAN NEWS GAME-CHANGER
Blessure dernière minute, retour surprise, météo extrême → RECALCULER λ et EV.

## ÉTAPE 7 — PRÉSENTATION FINALE
Pour chaque bet : type marché (2-way/3-way), pick, cote, EV%, confiance, Kelly¼, score prédit, 3-6 arguments. Ordre : ⭐⭐⭐⭐⭐ 2-way → ⭐⭐⭐⭐ → ⭐⭐⭐ → 1X2 avec warning. Fast-skip : match sous ⭐⭐⭐⭐ au filtre forme/H2H → 2-3 lignes + SKIP.

---

## RÈGLES (1-36)

**Filtres obligatoires (appliquer AVANT Poisson) :**
1. EV < +3% = NE RECOMMANDE PAS
2. Bets safe = 2-way uniquement
3. 1X2 cap ⭐⭐⭐, Kelly /2
4. Toujours vérifier absences avant calcul
5. Ne jamais ignorer la météo
6. 1st leg playoff = réduire λ (gestion)
7. Info game-changer → RECALCULER
8. Diversifier les marchés (pas tout en 1X2)
9. Max 6-8 bets/jour
10. Documenter les échecs

**Forme et motivation :**
11. Ne jamais sous-estimer motivation survie/relégation adverse (valide si ≤5 matchs sans W)
12. Forme récente (2-3 derniers matchs) pèse autant que tendance longue
13. Forme CL = forme réelle. Joueur série chaude = +0.10-0.15 λ (TOUTES compétitions)
14. Risque identifié dans l'audit → CONSÉQUENCES sur le marché. Risque identifié mais ignoré = erreur méthodologique
15. AH ≥ -0.75 = quasi 3-way. Privilégier marchés indépendants du résultat
16. Rotation "possible" ≠ confirmée. JAMAIS intégrer absence spéculative

**Tier system :**
17. TOUJOURS vérifier le Tier AVANT analyse. Tier 2 = ⭐⭐⭐ max + Kelly /2
18. EV > +20% sur Tier 2 sans xG = RED FLAG
19. Tier 1.5 → consulter sources spécialisées listées
20. MLS début saison (→ ~J5) = cap ⭐⭐⭐
21. K-League/A-League = Tier 2. J-League = Tier 1.5. Liga BetPlay = Tier 2 (⭐⭐ max)
22. Ligues exotiques — data minimum sinon NE PAS PARIER. Rounds 1-3 = SKIP
23. Fast-skip : sous ⭐⭐⭐⭐ au filtre forme/H2H → résumé 2-3 lignes + SKIP

**Cotes et marchés :**
24. Vérification cotes réelles OBLIGATOIRE. EV recalculé avec cote réelle < +3% → SKIP
25. LOI DU 2-WAY : scanner TOUS marchés 2-way. Marché moins populaire = plus de value

**Corners :**
26. Biais H2H corners : victoires faciles du favori = stats non représentatives → fiabilité /2
27. Under corners INTERDIT si : favori <1.60 + bloc bas adverse + favori ≥6 corners/match
28. Forme set-pieces active (2+ buts sur corners en 3 matchs) → Under corners déconseillé

**Joueurs et streaks :**
29. Scanner RETOURS joueurs clés (suspension/blessure). Buteur série chaude revient = +0.10-0.20 λ obligatoire
30. PLAFOND STREAK : ajustement λ max ±0.15 sur streak seule. Vérifier si streak structurelle (xG) ou fragile (sous-performance finishing). Équipe qui tire 10+/match mais ne marque pas = streak FRAGILE
31. SIGNAUX CONTRADICTOIRES (≥5 matchs chacun, même marché, sens opposés) → cap ⭐⭐⭐ auto + Kelly /2

**CL / Coupes :**
32. HERITAGE FACTOR CL : JAMAIS parier victoire adversaire contre club historique CL (≥3 titres : Real, Bayern, Milan, Liverpool, Barça) chez lui en knockout. Seuls marchés autorisés : Over/Under, BTTS, corners, props. S'applique UNIQUEMENT en CL knockout (pas groupes, pas EL, pas domestique)
33. NOUVEAU COACH ≤5 matchs = échantillon insuffisant → cap ⭐⭐⭐. Ne JAMAIS extrapoler moyenne ≤5 matchs comme tendance fiable
34. 6-POINTER RELÉGATION ≠ MATCH FERMÉ si écart de qualité. Seuil : ≥10 matchs sans victoire = équipe brisée (R34 > R11). Vérifier volume tirs : streak 0 but + tirs élevés = FRAGILE. Under/BTTS Non = marchés dangereux dans ce contexte → cap ⭐⭐⭐
35. RETOUR CL ≥3 BUTS DÉFICIT : stats offensives habituelles de l'équipe qui MÈNE = INVALIDES (elle joue en survie, pas en attaque). NE JAMAIS baser BTTS sur streak offensive d'une équipe protégeant gros agrégat. Marchés fiables : Over buts dom, victoire dom. Marchés dangereux : BTTS, Under
36. FACTEUR TERRAIN : (1) Synthétique↔naturel = -0.10 à -0.15 λ (stats dom sur synthétique GONFLÉES). (2) Pelouse lourde/boueuse = -0.05 λ total, tendance Under. (3) Altitude ≥2000m = +0.10 à +0.20 λ dom, -0.10 à -0.15 λ ext. Clubs clés : Bodø/Young Boys (synthé), Toluca/Pachuca (altitude), Bogotá (altitude)

**Régression et data quality (NOUVEAU v7.0) :**
37. RÉGRESSION xG OBLIGATOIRE : avant CHAQUE analyse, comparer buts réels vs xG sur les 5-10 derniers matchs. Si surperformance ≥ +30% (ex : 8 buts réels pour 5.0 xG) → l'équipe est "lucky", régression probable → favoriser Under/BTTS Non. Si sous-performance ≥ -30% (ex : 3 buts pour 6.0 xG) → l'équipe est "unlucky", explosion offensive probable → favoriser Over/BTTS Oui. **NE JAMAIS parier dans le sens de la variance.** Un buteur qui surperforme constamment son xG = vérifier si c'est du talent (post-shot xG élevé) ou de la chance (xGOT normal). Impact absences sur xG : buteur principal absent = -0.4 à -0.6 xG/match, défenseur clé absent = +0.5 à +0.8 xGA concédés.
38. FIXTURE CONGESTION : si une équipe joue son 3ème match en 8 jours ou moins → **malus -0.10 à -0.20 λ**. Match CL/EL midweek suivi d'un match domestique le weekend = vérifier rotation et fatigue physique. Cumulable avec absence joueur clé. Les équipes en congestion concèdent plus en 2ème MT (fatigue musculaire 60-75'). Impact renforcé si l'équipe a voyagé (déplacement CL ext → dom domestique = -0.15 λ minimum). Toujours vérifier le calendrier des 10 derniers jours des deux équipes AVANT d'analyser.
39. DROPPING ODDS (mouvement de cotes) : si la cote du marché ciblé a **baissé de >10% en 24h** → le sharp money est déjà passé, la value a probablement disparu. Recalculer EV avec la nouvelle cote — si EV < +3% → SKIP. Si la cote **MONTE de >10%** → information négative probable (blessure confirmée, rotation, météo) → investiguer la raison AVANT de jouer. Ne JAMAIS traiter un mouvement de cote comme un "tip" — c'est un SYMPTÔME d'information, pas l'information elle-même. Diagnostiquer le POURQUOI.
40. ANTI-BIAIS CHECKLIST (4 questions AVANT chaque bet) : (1) **Recency bias :** est-ce que je surpondère le dernier résultat alors que les xG disent autre chose ? (2) **Confirmation bias :** est-ce que je cherche uniquement des preuves qui confirment mon penchant ? Forcer 1 argument CONTRE avant de valider. (3) **Favorite bias / Fan tax :** est-ce que je paie un premium sur un gros club parce qu'il est "gros" ? La cote reflète-t-elle la proba réelle ou la popularité ? (4) **Risque ignoré :** y a-t-il un risque que j'ai identifié mais que j'écarte volontairement ? (Si oui → R14 s'applique, conséquences obligatoires). **Si oui à 1+ question → réduire confiance d'un cran minimum.**
41. xG PAR TIR (QUALITÉ vs QUANTITÉ) : ne JAMAIS confondre "beaucoup de tirs" avec "beaucoup de danger". Vérifier **xG/shot** avant de conclure qu'une équipe est offensive. Seuils : xG/shot < 0.08 = qualité faible (spam lointain, pas de vrai danger) malgré volume élevé → ne PAS baser un Over sur ce volume. xG/shot > 0.12 = haute qualité (occasions nettes, dans la surface) → signal fiable pour Over. Une équipe qui tire 15 fois avec 0.5 xG total n'est PAS la même qu'une qui tire 8 fois avec 2.0 xG. Filtrer le bruit des tirs non cadrés hors surface.

---

## ÉCHECS DOCUMENTÉS (résumé — détails dans les transcripts)

| # | Match | Bet perdu | Leçon → Règle |
|---|---|---|---|
| 1 | Cagliari 0-2 Lecce (16/02) | 1X2 @2.15 ⭐⭐⭐⭐⭐ | Jamais 1X2 en 5⭐ → R2-R3 |
| 2 | Lens 2-2 Monaco (21/02) | AH -0.75 @1.85 ⭐⭐⭐⭐⭐ | Forme CL=réelle, joueur série chaude, risque identifié=conséquences → R13-R14-R15 |
| 3 | Hanovre 0-0 Dresde (22/02) | BTTS @1.72 ⭐⭐⭐⭐⭐ | Tier 2 sans xG, EV >+20% = RED FLAG → R17-R18 |
| 4 | Wolves 1-0 Liverpool (28/02) | Under 9.5 corners @1.72 | Bloc bas + favori <1.60 = corners explosent → R26-R27-R28 |
| 5 | Dortmund-Bayern (28/02) | Over 2.5 estimé @1.65 | Cote réelle @1.33, EV -4% → R24 |
| 6 | Tijuana 1-2 Santos (09/03) | BTTS Non @2.10 ⭐⭐⭐⭐ | Retour joueur clé non scanné, streak fragile, signaux contradictoires → R29-R30-R31 |
| 7 | Real 3-0 City (11/03) | City victoire @2.00 ⭐⭐⭐⭐ | Heritage factor CL knockout → R32 |
| 8 | Liverpool 1-1 Tottenham (15/03) | Over 3.5 envisagé @2.20 ⭐⭐⭐⭐ (SKIP) | Nouveau coach ≤5 matchs = échantillon insuffisant → R33 |
| 9 | Cremonese 1-4 Fiorentina (16/03) | BTTS Non @1.95 ⭐⭐⭐⭐ | 6-pointer ≠ fermé si écart qualité, streak 0 but fragile → R34 |
| 10 | Sporting 5-0 Bodø (17/03) | BTTS Oui envisagé @1.85 (SKIP R24) | Retour CL gros déficit = stats équipe qui mène invalides → R35 |

---

## CHANGELOG (versions majeures)
- **v5.0** (21/02) : Refonte 2-way, Tier system, échecs documentés
- **v5.8** (04/03) : Sources par ligue, insiders Twitter, règles corners, Liga MX
- **v6.0** (11/03) : Heritage Factor CL (R32), post-mortem UCL 8es
- **v6.4** (17/03) : R33-R36 (nouveau coach, 6-pointer, retour CL déficit, terrain). Compression 810→227 lignes (-72%)
- **v7.0** (17/03) : **5 nouvelles règles issues de la recherche sharp/pro.** R37 régression xG (ne jamais parier dans le sens de la variance). R38 fixture congestion (3ème match en 8j = -0.10 à -0.20 λ). R39 dropping odds (mouvement >10% = value disparue ou info négative). R40 anti-biais checklist (4 questions obligatoires avant chaque bet). R41 xG/shot quality (filtrer le bruit des tirs non dangereux). **41 règles totales. Objectif : 0 erreur évitable avant lancement StratEdge 31/03.**
