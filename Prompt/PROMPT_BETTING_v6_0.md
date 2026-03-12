# PROMPT BETTING v6.0 — StratEdge Pronos
## Mis à jour le 11/03/2026 (v6.0 : post-mortem UCL 8es — Heritage Factor CL, jamais parier contre un club historique CL chez lui en knockout)

---

## OBJECTIF
Identifier des VALUE BETS avec un Expected Value (EV) positif ≥ +3% en utilisant une méthodologie rigoureuse combinant analyse statistique, contextuelle, et sources d'information de premier plan.

---

## RÈGLE FONDAMENTALE — MARCHÉS 2-WAY OBLIGATOIRES POUR LES BETS "SAFE"

> **Un bet "safe" (⭐⭐⭐⭐ ou ⭐⭐⭐⭐⭐) ne peut JAMAIS être un marché 1X2 (3-way).**
>
> Pourquoi ? Parce qu'un marché 1X2 a 3 résultats possibles (victoire, nul, défaite). Même avec une probabilité estimée à 55%, il reste 45% de chances de perdre. Ce n'est PAS safe.
>
> **Les bets safe doivent être sur des marchés 2-way uniquement :**
> - Over/Under (2.5, 3.5, 1.5, etc.)
> - BTTS Oui / Non
> - Asian Handicap (2 résultats : gagné ou perdu, avec possibilité de remboursement sur certaines lignes)
> - Double Chance (1X, X2, 12)
> - Équipe marque Oui/Non (ex: Lecce marque Oui/Non)
> - Mi-temps / Fin de match (variantes 2-way)
>
> **⚠️ ATTENTION — Asian Handicap ≥ -0.75 = quasi 3-way déguisé.**
> Un AH -0.75 exige une victoire d'au moins 1 but pour gagner. Si l'équipe mène 2-0 puis se fait rattraper 2-2, c'est PERDU.
> Pour un bet "safe", privilégier les marchés INDÉPENDANTS du résultat final : Over/Under, BTTS, Double Chance.
> Les AH restent autorisés mais avec prudence accrue au-delà de -0.5.
>
> **Les marchés 1X2 (victoire pure) restent autorisés mais :**
> - Maximum ⭐⭐⭐ de confiance (jamais 4 ou 5 étoiles)
> - Mise Kelly divisée par 2 par rapport au calcul normal
> - Toujours présentés comme "value bet risqué", jamais comme "safe"

### Pourquoi cette règle ? L'échec Cagliari-Lecce (16/02/2026)

**Le cas d'étude :** On avait recommandé Cagliari victoire @2.15 en ⭐⭐⭐⭐⭐ (55% proba, EV +17%). Résultat : **Cagliari 0-2 Lecce**.

**Ce qui a merdé :**
1. **Marché 3-way** → 55% de victoire = 45% d'échec. C'est un coin flip amélioré, PAS un bet safe.
2. **Surpondération des stats historiques** → On a trop regardé "Cagliari a battu Juve 1-0 et Vérone 4-0" sans peser assez la défaite fraîche 0-2 à Roma qui montrait un retour à la réalité.
3. **Sous-estimation de la motivation adversaire** → Lecce 17e, zone de relégation, jouait sa survie. La pression positive du "dos au mur" a été ignorée.
4. **Fausse sécurité des stats offensives de Lecce** → "Pire attaque de Serie A" ne veut pas dire "ne marquera jamais". Lecce avait battu Udinese 2-1 la semaine d'avant (Banda but 90e).
5. **Banda suspendu ≠ Lecce neutralisé** → D'autres joueurs (Gandelman, Ramadani) ont pris le relais. On a surestimé l'impact d'un seul absent.

**Leçon intégrée :** Si on avait misé sur Under 2.5 @1.85 (marché 2-way, proba 68%, EV +26%), on aurait aussi perdu (0-2 = 2 buts = Under 2.5 GAGNÉ ✅). Et si on avait misé BTTS Non @1.67 (proba 66%), on aurait GAGNÉ (seul Lecce a marqué). **Les marchés 2-way avec des probas à 65%+ sont structurellement plus fiables que les 1X2 à 55%.**

---

## ÉTAPE 0 — VÉRIFICATION DU CHAMPIONNAT (TIER SYSTEM)

> **AVANT toute analyse, vérifier dans quel TIER se situe le championnat.**
> Le tier détermine le PLAFOND de confiance et les sources à utiliser.
> **Un championnat mal couvert = données faibles = Poisson aveugle = erreurs.**

### 🟢 TIER 1 — "Big 5" + Coupes européennes = ANALYSES COMPLÈTES

| Championnat | xG FBref | xG Understat | Médias | Confiance max |
|---|---|---|---|---|
| 🏴󠁧󠁢󠁥󠁮󠁧󠁿 Premier League | ✅ | ✅ | 🟢 Massive | ⭐⭐⭐⭐⭐ |
| 🇪🇸 La Liga | ✅ | ✅ | 🟢 Très bonne | ⭐⭐⭐⭐⭐ |
| 🇮🇹 Serie A | ✅ | ✅ | 🟢 Très bonne | ⭐⭐⭐⭐⭐ |
| 🇩🇪 Bundesliga (1ère div.) | ✅ | ✅ | 🟢 Très bonne | ⭐⭐⭐⭐⭐ |
| 🇫🇷 Ligue 1 | ✅ | ✅ | 🟢 Bonne | ⭐⭐⭐⭐⭐ |
| 🏆 Champions League | ✅ | ✅ | 🟢 Massive | ⭐⭐⭐⭐⭐ |
| 🏆 Europa League | ✅ | ⚠️ Partiel | 🟢 Bonne | ⭐⭐⭐⭐⭐ |

**Toutes les étapes du prompt s'appliquent normalement. xG réels disponibles pour calibrer les λ.**

### 🟡 TIER 1.5 — Ligues secondaires AVEC xG = ANALYSES POSSIBLES (plafond ⭐⭐⭐⭐)

| Championnat | xG sources | Médias/News | Confiance max |
|---|---|---|---|
| 🇳🇱 Eredivisie | FBref ✅, FootyStats ✅, xGscore ✅ | 🟡 Correcte (EN) | ⭐⭐⭐⭐ |
| 🇵🇹 Liga Portugal | FBref ✅, FootyStats ✅, xGscore ✅ | 🟡 Correcte (PT/EN) | ⭐⭐⭐⭐ |
| 🇺🇸 MLS | FBref ✅, ASA ✅, FootyStats ✅, xGscore ✅ | 🟡 Correcte (EN) | ⭐⭐⭐⭐ |
| 🏆 Conference League | FBref ⚠️ Partiel | 🟡 Moyenne | ⭐⭐⭐⭐ |
| 🇯🇵 J-League | football-lab.jp ✅, sporteria.jp ✅, FBref ⚠️ | 🟡 Partielle (JP/EN) | ⭐⭐⭐⭐ |

**IMPORTANT :** Ces ligues ont des xG disponibles, donc le Poisson peut être calibré correctement. MAIS la couverture médiatique est moins dense (blessures, compos, news de dernière minute). **Jamais ⭐⭐⭐⭐⭐** même si l'EV est énorme. Réduire la confiance d'un cran systématiquement.

**⚠️ MLS — WARNING SPÉCIAL :** En début de saison (février-mars), les données saison en cours sont quasi inexistantes. Se baser sur la saison N-1 + transferts intersaison. Opening Day = **confiance max ⭐⭐⭐**.

**⚠️ J-League — WARNING SPÉCIAL :** Les données xG sont disponibles sur football-lab.jp et sporteria.jp mais en japonais. Utiliser shogunsoccer.com / @R_by_Ryo pour les analyses en anglais. Début de saison J-League (février-mars) = **confiance max ⭐⭐⭐**.

### 🔴 TIER 2 — Championnats SANS xG fiable = INTERDITS pour bets safe

| Championnat | Problème | Confiance max |
|---|---|---|
| 🇩🇪 2. Bundesliga | PAS de xG → Poisson aveugle | ⭐⭐⭐ max |
| 🏴󠁧󠁢󠁥󠁮󠁧󠁿 Championship | Pas de xG, résultats erratiques | ⭐⭐⭐ max |
| 🇮🇹 Serie B | Quasi rien | ⭐⭐ max |
| 🇫🇷 Ligue 2 | Quasi rien | ⭐⭐ max |
| 🇪🇸 Segunda | Quasi rien | ⭐⭐ max |
| 🇲🇽 Liga MX | Pas de xG fiable, style imprévisible | ⭐⭐⭐ max |
| 🇦🇷 Liga Argentina | xG partiel, médias faibles en FR/EN | ⭐⭐⭐ max |
| 🇹🇷 Süper Lig | Éviter complètement | ⭐⭐ max |
| 🇰🇷 K-League | PAS de xG fiable, data anglophone limitée | ⭐⭐⭐ max |
| 🇦🇺 A-League | Pas de xG fiable, couverture minimale | ⭐⭐⭐ max |
| 🇨🇴 Liga BetPlay | Quasi rien de fiable en dehors sites colombiens | ⭐⭐ max |

**RÈGLE ABSOLUE :** Sur un championnat Tier 2, **JAMAIS de ⭐⭐⭐⭐ ou ⭐⭐⭐⭐⭐**, mise Kelly divisée par 2, et toujours mentionner dans la présentation : "⚠️ Championnat Tier 2 — données limitées".

> **⚠️ LIGUES EXOTIQUES (K-League, A-League, Liga BetPlay, etc.) — RÈGLE DATA MINIMUM :**
> Si les données suivantes ne sont pas disponibles → **NE PAS PARIER** :
> - Forme récente des 5 derniers matchs
> - H2H au stade spécifique (domicile/extérieur)
> - Informations sur les absences du match
>
> Data insuffisante = risque inconnu = Poisson aveugle. **Rounds 1-3 de saison = SKIP systématique** sur toutes les ligues exotiques.

### Pourquoi cette règle ? L'échec Hanovre 0-0 Dresde (22/02/2026)

**Le cas d'étude :** On avait recommandé BTTS Oui @1.72 en ⭐⭐⭐⭐⭐ (74.7% proba, EV +28.5%) sur Hanovre-Dresde en 2. Bundesliga. Résultat : **0-0**.

**Ce qui a merdé :**
1. **Pas de xG disponible en 2. Bundesliga** → Le Poisson était calibré sur des buts réels bruts, pas sur la qualité des occasions
2. **Hanovre avait marqué 10 buts en 4 matchs** → λ gonflé artificiellement par une série chaude, sans xG pour tempérer
3. **Les stats brutes (BTTS 100% dom, 82% ext) paraissaient écrasantes** → MAIS sans xG, impossible de savoir si c'était de la surperformance
4. **EV à +28.5% aurait dû être un RED FLAG** → Un EV aussi élevé sur un championnat sans xG = probablement une surestimation
5. **Le modèle a donné ⭐⭐⭐⭐⭐ sur un championnat qu'il ne maîtrise pas** → Erreur structurelle

**Leçon intégrée :** Un EV > +20% sur un championnat Tier 2 est SUSPECT, pas rassurant. Plus l'EV paraît élevé sans xG pour le valider, plus il faut se méfier. Les données brutes flattent mais ne reflètent pas la réalité sous-jacente.

---

## ÉTAPE 1 — IDENTIFICATION DES MATCHS
- Lister tous les matchs du jour/période demandée
- **NOUVEAU v5.2 :** Vérifier le TIER de chaque championnat (Étape 0). Prioriser les matchs Tier 1 > Tier 1.5 > Tier 2
- Prioriser : Ligue des Champions, Europa League, Top 5 ligues européennes, Eredivisie, Liga Portugal, MLS, J-League
- Éliminer immédiatement les matchs sans intérêt (match nul qualificatif, rotation massive, etc.)
- Identifier les contextes spéciaux : derby, relégation, qualification, dernier match de saison

---

## ÉTAPE 2 — COLLECTE DE DONNÉES PAR MATCH

Pour chaque match retenu, collecter :

### A. Stats de base (obligatoires)
- Classement, points, forme récente (5 derniers matchs)
- GF/match et GA/match (saison + domicile/extérieur)
- Over/Under rates (2.5 et 3.5) sur 10 et 20 derniers matchs
- BTTS rate (saison + dom/ext)
- Clean sheet rate
- Buts marqués/encaissés par mi-temps (1MT vs 2MT)

### B. xG et stats avancées (Tier 1 et 1.5 obligatoire)
- xG créés et concédés par match (FBref, Understat)
- xG dom vs ext
- Différence buts réels vs xG (surperformance/sous-performance)
- xPTS vs PTS réels (indicateur de régression)
- Tirs/match, tirs cadrés/match
- PPDA (pressing intensity)

### C. Absences et compositions
- Blessures confirmées (sources : club officiel, journalistes Tier 1)
- Suspensions (cartons cumulés, sanctions)
- Joueurs incertains (game-time decisions)
- **NOUVEAU v5.1 :** Rotation "possible" ≠ rotation confirmée. Ne JAMAIS intégrer une absence spéculative dans le modèle.
- Impact estimé de chaque absence sur les λ

### D. H2H (Head to Head)
- 5-10 derniers matchs entre les deux équipes
- Patterns : Over/Under, BTTS, scores exacts récurrents
- Avantage domicile/extérieur dans le H2H
- ⚠️ Ne pas surpondérer le H2H si contexte très différent (ex: promu vs non-promu)

### E. Sources globales (Tier 1 — toutes ligues)
- **xG réels :** FBref (fbref.com), Understat (understat.com), xGscore.io
- **Stats O/U et BTTS :** FootyStats.org, BetExplorer (H2H cotes historiques)
- **Simulations Monte Carlo :** Dimers.com
- **Arbitre désigné :** Stats de cartons/match, penalties/match, profil (sévère/laxiste)
- **Reddit :** r/soccer (news de dernière minute), r/SoccerBetting (consensus communautaire, lignes qui bougent)
- **Conditions météo :** Si match extérieur, vérifier vent, pluie, températures extrêmes
- **Généralistes :** Sofascore, MakeYourStats (xG par ligue)

### E quater. Comptes Twitter/X INSIDERS par ligue (Big 5) — OBLIGATOIRE pour blessures/compos

> ⚠️ **RÈGLE D'UTILISATION :** Avant toute analyse Tier 1, scanner AU MOINS un compte insider de la ligue concernée pour détecter une blessure de dernière minute non encore pricée par le marché. C'est là que se trouve l'edge réel.

#### 🏴󠁧󠁢󠁥󠁮󠁧󠁿 PREMIER LEAGUE
| Compte | Spécialité |
|---|---|
| @David_Ornstein | The Athletic — insider blessures/transferts tous clubs PL |
| @FabrizioRomano | Transferts, blessures confirmées (fiable sur "here we go") |
| @MattLawTelegraph | Telegraph — compositions et news clubs anglais |
| @ChrisWheelerDM | Daily Mail — insider PL généraliste |
| @SkySportsPL | Compos officielles dès publication |
| @premierleague | Compte officiel — compositions 1h avant coup d'envoi |
| @WhoScored | Stats live, notes joueurs, compositions confirmées |

#### 🇪🇸 LA LIGA
| Compte | Spécialité |
|---|---|
| @mohamedbouhafsi | RMC Sport — insider transferts/blessures Real & Barça |
| @DeportesRTVE | Actualités La Liga en espagnol |
| @MarcaEn | Marca en anglais — news Real Madrid |
| @mundodeportivo | Barça-centré, blessures et compositions |
| @LaLigaEN | Compte officiel EN — compositions officielles |
| @ffpolo | Journaliste terrain La Liga (fiable sur compos) |
| @Santi_Aouna | Insider transferts ES/FR |

#### 🇮🇹 SERIE A
| Compte | Spécialité |
|---|---|
| @FabrizioRomano | Insider transferts + blessures Serie A (très fiable) |
| @DiMarzio | Sky Sport Italia — LE référence insider Serie A |
| @SerieA_EN | Compte officiel EN — compositions |
| @GianlucaDiMarzio | Blessures confirmées, compositions de dernière minute |
| @TuttoMercatoWeb | Agrégateur news Serie A (italien) |
| @SkySport | Compositions officielles 1h avant |
| @Gazzetta_it | Gazzetta dello Sport — stats et news |

#### 🇩🇪 BUNDESLIGA
| Compte | Spécialité |
|---|---|
| @Bundesliga_EN | Compte officiel EN — compositions officielles |
| @MiaSanMia | Insider Bayern Munich (blessures, compos) |
| @iMiaSanMia | Bayern Munich EN — news détaillées |
| @bvb09en | BVB officiel EN |
| @Sky_Bundesliga | Compositions et news live |
| @christophkeine | Journaliste insider Bundesliga |
| @kicker | Référence news Bundesliga (all clubs) |

#### 🇫🇷 LIGUE 1
| Compte | Spécialité |
|---|---|
| @mohamedbouhafsi | RMC Sport — insider PSG + Ligue 1 |
| @RMCsport | Blessures et compos Ligue 1 en temps réel |
| @lequipe | L'Équipe officiel — compositions, blessures |
| @telefoot_chaine | Compositions confirmées avant match |
| @Ligue1UberEats | Compte officiel — compos 1h avant |
| @Tangi_LB | Journaliste L'Équipe — insider clubs L1 |
| @pierrerongier | Journaliste terrain L1 fiable |

#### 🏆 CHAMPIONS LEAGUE / COUPES EUROPÉENNES
| Compte | Spécialité |
|---|---|
| @FabrizioRomano | Blessures CL tous clubs |
| @ChampionsLeague | Compte officiel — compositions |
| @OptaJoe | Stats CL avancées, records, xG |
| @UEFA | Infos officielles arbitres, suspensions |

### E bis. Sources spécialisées Tier 1.5 (Eredivisie, Liga Portugal, MLS, J-League)

| Ligue | Sources data | Sources news/insiders |
|---|---|---|
| 🇳🇱 Eredivisie | FootyStats, xGscore, FBref | @EredivisieMike |
| 🇵🇹 Liga Portugal | PortuGoal.net, FootyStats, xGscore | @PsoccerCOM |
| 🇺🇸 MLS | ASA (americansocceranalysis.com), FootyStats, xGscore | @TomBogert |
| 🇯🇵 J-League | football-lab.jp, sporteria.jp, FBref partiel | shogunsoccer.com, @R_by_Ryo (xG EN), @JLeagueFra (FR), @J_League_En (officiel EN) |

### E ter. Sources spécialisées Tier 2 — Ligues exotiques

> ⚠️ Ces sources sont INDISPENSABLES avant de parier sur ces championnats. Sans elles, le Poisson est aveugle.

#### 🇰🇷 K-League
| Source | URL / Handle | Utilité |
|---|---|---|
| K League United | kleagueunited.com / @KLeagueUnited | Stats, classements, résumés EN |
| K League France | @KLeagueFr | News FR, résultats |
| K League officiel | @kleague | Infos officielles, compositions |

#### 🇯🇵 J-League (compléments Tier 1.5)
| Source | URL / Handle | Utilité |
|---|---|---|
| Shogun Soccer | shogunsoccer.com / @R_by_Ryo | xG en anglais, analyse tactique |
| J-League France | @JLeagueFra | News FR |
| J-League officiel EN | @J_League_En | Infos officielles EN |
| Football-Lab | football-lab.jp | xG officiel JP (données les plus fiables) |
| Sporteria | sporteria.jp | Stats avancées JP |

#### 🇦🇺 A-League
| Source | URL / Handle | Utilité |
|---|---|---|
| AusSportsBetting | aussportsbetting.com | Form, H2H, cotes historiques |
| Ultimate A-League | ultimatealeague.com | Stats détaillées |
| ALeague Stats | aleaguestats.com | Data par match |
| The Stats Zone | thestatszone.com | Stats avancées |
| A-League Bets | @ALeagueBets | Communauté betting AUS |
| A-League Wrap-Up | @ALeagueWrapUp | Résumés, news |
| Sites clubs officiels | — | Blessures/absences (OBLIGATOIRE) |

#### 🇲🇽 Liga MX
> ⚠️ **RAPPEL TIER 2** : Pas de xG officiel fiable. Ces sources compensent partiellement ce manque. Scanner **obligatoirement** au moins 1 insider Twitter + 1 site stats avant chaque analyse Liga MX.

**📊 Sites de statistiques (par ordre de priorité)**

| Source | URL | Ce qu'on y trouve |
|---|---|---|
| **FootyStats Liga MX** ⭐ | footystats.org/mexico/liga-mx | **MEILLEURE SOURCE xG Liga MX** — Over/Under, BTTS, corners, xG estimé par équipe, H2H, forme domicile/extérieur |
| **FotMob Liga MX** ⭐ | fotmob.com/leagues/230/stats/liga-mx | Stats en temps réel, ratings joueurs, cartons, classement |
| **FBref Liga MX** | fbref.com/en/comps/31/ | xG partiel, données avancées (pas toujours à jour) |
| **Sofascore** | sofascore.com → Liga MX Clausura | Stats live, compos confirmées 1h avant, historique H2H |
| **Dimers Liga MX** | dimers.com/lmx/picks | Simulations Monte Carlo, probabilités de victoire |
| **xGscore Liga MX** | xgscore.io/liga-mx | xG par match (utile pour vérifier la cohérence) |
| **BetExplorer Liga MX** | betexplorer.com/soccer/mexico/ | Cotes historiques, résultats H2H avec cotes |

**📰 Sites d'actualité mexicains (blessures, compositions, rumeurs)**

| Source | URL | Ce qu'on y trouve |
|---|---|---|
| **Medio Tiempo** ⭐ | mediotiempo.com | **LE site de référence MX** — blessures, compos, news clubs en temps réel |
| **RÉCORD** ⭐ | record.com.mx | Journaux terrain, insiders clubs, rumeurs transferts |
| **ESPN Deportes MX** | espndeportes.espn.com/futbol/liga?nombre=mex.1 | Alineaciones officielles, news transfers, analyses |
| **TUDN** | tudn.com/futbol/liga-mx | Média officiel Televisa — compos, résultats, news |
| **Viva Liga MX** | vivaligamx.com | Communauté EN — analyses tactiques, news clubs |

**🐦 Comptes Twitter/X — Journalistes & Insiders**

| Compte | Profil | Pourquoi le suivre |
|---|---|---|
| **@mexicoworldcup** ⭐⭐ | Tom Marshall (ESPN) | **LE journaliste de référence Liga MX en anglais** — blessures, compos, insiders clubs. 104k tweets, suit la Liga MX depuis +15 ans |
| **@ESPNmx** ⭐ | ESPN México officiel | Compos officielles publiées 1h avant le match, news en temps réel |
| **@TUDN** ⭐ | TUDN officiel | Média Televisa — compos confirmées, résumés, live |
| **@mediotiempo** | Medio Tiempo | News blessures/suspensions en espagnol |
| **@record_mx** | Diario Récord | Insiders clubs (América, Chivas, Cruz Azul surtout) |
| **@LigaBBVAMX** | Compte officiel Liga MX | Compositions officielles publiées ~1h avant le match |
| **@cesarhfutbol** | César Hernandez (journaliste) | Insider Liga MX EN — co-host Mexican Soccer Show |
| **@NayibMoran** | Nayib Morán (journaliste) | Analyses tactiques Liga MX EN, blessures clubs |
| **@VivaLigaMx** | Viva Liga MX (media EN) | Communauté EN, threads analyse, news clubs |
| **@BetLigaMx** | Communauté betting Liga MX | Picks, lignes qui bougent, sharp money Liga MX |
| **@WisoVazquez** | Wiso Vazquez (journaliste) | News Liga MX EN, compos |

**🎙️ Ressources complémentaires**

| Source | Format | Utilité |
|---|---|---|
| Mexican Soccer Show | Podcast (Apple/Spotify) | Analyse hebdomadaire Liga MX EN — Marshall + Hernandez + Morán |
| r/LigaMX | Reddit | News de dernière minute, réactions communauté, blessures confirmées |
| Sofascore app | App mobile | Notification automatique compos 1h avant → idéal pour surveillance bets |

**⚡ WORKFLOW RECOMMANDÉ avant de parier en Liga MX :**
1. **FootyStats** → xG estimé, BTTS %, Over/Under % des 2 équipes
2. **@mexicoworldcup ou @ESPNmx** → blessures/absences confirmées
3. **@LigaBBVAMX ou Sofascore** → composition officielle (dès publication)
4. **Medio Tiempo** → contexte interne club (moral, rotation prévue, déclarations coach)
5. **BetExplorer** → cotes H2H historiques pour contexte

#### 🇨🇴 Liga BetPlay
| Source | URL / Handle | Utilité |
|---|---|---|
| FutbolRed | futbolred.com | Media de référence COL |
| MakeYourStats | makeyourstats.com | Stats ligue, xG estimé |
| Sofascore | sofascore.com | Stats match, compos |
| Win Sports | @WinSportsTV | Chaîne TV officielle COL, news |
| Gol Caracol | @GolCaracol | Journalistes terrain COL |

---

## ÉTAPE 3 — CONTEXTE TACTIQUE ET MOTIVATION

Pour chaque match, évaluer :

### Motivation / Enjeu
- Match de relégation / survie ? → AUGMENTER les λ offensifs (les deux équipes poussent)
- Match de qualification européenne ?
- Derby / rivalité historique ? → Ajustement émotionnel
- Match "mort" (rien à jouer) ? → SKIP ou réduire confiance
- **NOUVEAU v5 :** Motivation "dos au mur" de l'adversaire = facteur RÉEL, ne jamais ignorer

### Tactique attendue
- Le coach est-il offensif ou défensif ? (ex: Simeone = défensif, Knutsen = offensif)
- Formation attendue (4-3-3 offensif vs 5-4-1 bus)
- Game state probable : si l'équipe mène, va-t-elle défendre ou continuer ?
- Playoff 2nd leg : contexte de l'agrégat → impact MAJEUR sur les λ

### Joueur série chaude
- **NOUVEAU v5.1 :** Si un attaquant a marqué 2+ buts sur ses 2 derniers matchs (TOUTES compétitions confondues) → ajouter +0.10 à +0.15 au λ de son équipe
- Ne PAS cloisonner les stats domestiques et européennes

---

## ÉTAPE 4 — MODÉLISATION POISSON

### Calibration des λ

**λ = nombre moyen de buts attendus pour une équipe dans un match donné**

#### Base :
- Utiliser les xG dom/ext de la saison en cours (Tier 1) ou GF/GA moyen (Tier 2)
- Moyenne pondérée : 60% xG saison + 40% forme récente (5 derniers matchs)

#### Ajustements (additionner/soustraire) :
- Forme récente en feu (3+ victoires) : +0.10 à +0.20
- Forme récente catastrophique : -0.10 à -0.20
- vs défense faible (GA > 1.5/match) : +0.10 à +0.20
- vs défense forte (GA < 1.0/match) : -0.10 à -0.20
- Joueur clé absent (buteur principal) : -0.15 à -0.30
- Joueur clé de retour : +0.10 à +0.20
- **Joueur série chaude (2+ buts en 2 matchs)** : +0.10 à +0.15
- Enjeu relégation/qualification : +0.05 à +0.15
- Match "mort" : -0.10 à -0.20
- Conditions météo extrêmes : -0.05 à -0.15
- Avantage domicile (stade hostile) : +0.05 à +0.10
- Playoff 2nd leg — doit remonter : +0.20 à +0.40
- Playoff 1st leg — gestion : -0.10 à -0.20
- Gazon artificiel → naturel (ou inverse) : -0.05 à -0.10

### Calcul Poisson

Avec λ_total = λ_dom + λ_ext :

```
P(k buts) = (e^(-λ) × λ^k) / k!
```

Calculer P(0), P(1), P(2), P(3), P(4), P(5+)
→ En déduire P(Over 2.5), P(Over 3.5), P(Under 2.5), etc.

**P(BTTS) :**
- P(Dom marque 0) = e^(-λ_dom)
- P(Ext marque 0) = e^(-λ_ext)
- P(au moins un ne marque pas) = P(Dom=0) + P(Ext=0) - P(Dom=0) × P(Ext=0)
- **P(BTTS) = 1 - P(au moins un ne marque pas)**

---

## ÉTAPE 4bis — BLENDING (MÉLANGE DES SOURCES)

Le Poisson seul est trop conservateur (basé sur les moyennes de saison). On le mélange avec les tendances récentes.

### Poids de blending :
| Source | Poids |
|---|---|
| Poisson (λ calibré) | **40%** |
| Forme récente (3-5 derniers matchs) | **25%** |
| Tendance domicile/extérieur (10-20 matchs) | **20%** |
| Tendance adverse ext/dom (10-20 matchs) | **15%** |

### Formule :
```
P(final) = 0.40 × P(Poisson) + 0.25 × P(forme récente) + 0.20 × P(dom trends) + 0.15 × P(ext trends)
```

Exemple : Si Poisson donne Over 2.5 = 44%, mais les 7 derniers matchs = 100% Over 2.5, dom trends = 70%, ext trends = 70% :
→ P(final) = 0.40×44 + 0.25×100 + 0.20×70 + 0.15×70 = 17.6 + 25 + 14 + 10.5 = **67.1%**

---

## ÉTAPE 5 — CALCUL DE L'EXPECTED VALUE (EV) ET CONFIANCE

### Formule EV :
```
EV = (P(réelle) × Cote) - 1
```

Si EV > 0 → bet à value positive
Si EV < +3% → NE PAS RECOMMANDER (edge trop faible pour compenser la marge d'erreur)

### Grille de confiance — marchés 2-way :

| Étoiles | EV minimum | P(réelle) min | Mise Kelly¼ max |
|---------|-----|-----------|--------|
| ⭐⭐⭐⭐⭐ | +10%+ | 65% | 4-6% bankroll |
| ⭐⭐⭐⭐ | +5% à +10% | 58% | 2-4% bankroll |
| ⭐⭐⭐ | +3% à +5% | 55% | 1-2% bankroll |

### Grille de confiance — marchés 1X2 (3-way) — PLAFOND ⭐⭐⭐ :

| Étoiles | EV minimum | P(réelle) min | Mise Kelly¼ max |
|---------|-----|-----------|--------|
| ⭐⭐ | +3% à +15% | 50% | 1% bankroll |
| ⭐⭐⭐ | +15%+ | 55% | 1.5-2% bankroll max |

> **Jamais de 1X2 en 4 ou 5 étoiles.** Même si l'EV est énorme, le risque structurel du 3-way empêche de le qualifier de "safe".

### Critère Kelly (quart-Kelly) :
- Kelly% = (EV / (Cote - 1)) / 4
- Ne jamais dépasser 6% sur un seul bet
- **NOUVEAU v5 :** Sur un 1X2, diviser la mise Kelly par 2

---

## ÉTAPE 5bis — DEVIL'S ADVOCATE (AUDIT CONTRADICTOIRE)

**OBLIGATOIRE avant toute recommandation.**

Pour chaque bet envisagé, identifier 3-4 risques :
- **Risque 1** : Scénario le plus probable qui fait perdre le bet
- **Risque 2** : Facteur sous-estimé ou ignoré
- **Risque 3** : Contexte tactique contraire
- **Risque 4** : Données potentiellement trompeuses

Pour chaque risque :
1. Estimer la probabilité (%)
2. Fournir un contre-argument
3. **NOUVEAU v5.1 :** Si le risque est sérieux → TIRER DES CONSÉQUENCES sur le choix de marché. Le marché recommandé doit être RÉSISTANT au scénario de risque.

---

## ÉTAPE 5ter — VÉRIFICATION COTES RÉELLES (OBLIGATOIRE avant toute recommandation)

> ⚠️ **Cette étape est NON NÉGOCIABLE. Aucun bet ne peut être recommandé sans avoir vérifié la cote réelle sur le marché.**

### Pourquoi cette règle ? L'erreur Dortmund-Bayern (28/02/2026)
**Le cas d'étude :** On avait estimé Over 2.5 Dortmund-Bayern à une cote ~1.65 → EV calculé +17%. Cote réelle sur Winamax/Betclic : **1.33**. EV réel : **-4% ❌**. Le marché avait déjà tout pricé. Bet annulé.

**Leçon intégrée :** Un EV calculé à partir d'une cote estimée = fiction. Le marché est efficace sur les gros matchs. **Toujours vérifier la cote réelle AVANT de conclure.**

### Sources à scanner dans l'ordre :
1. **Winamax.fr** → cotes souvent les plus hautes du marché FR
2. **Betclic.fr** → référence FR, large catalogue de marchés
3. **Oddschecker.com** → agrégateur multi-books, donne la meilleure cote disponible
4. **Flashscore.fr** (section cotes) → vue rapide multi-marchés

### Procédure obligatoire :
1. **Scanner le marché cible** (ex : Over 2.5, BTTS Non, Under 2.5) sur les sites ci-dessus
2. **Recalculer l'EV avec la cote réelle** : EV = (P(réelle) × Cote_réelle) - 1
3. **Appliquer le filtre :**
   - EV réel ≥ +3% → bet maintenu ✅
   - EV réel entre 0% et +3% → SKIP (edge trop faible) ❌
   - EV réel < 0% → SKIP immédiat, marché surcôté ❌
4. **Signaler l'écart** entre cote estimée et cote réelle dans la présentation finale

### Règle d'urgence (si scan impossible) :
Si les sites sont inaccessibles ou si le match est dans < 30 min → **utiliser une fourchette de cotes conservatrice** (baisser la cote estimée de 10-15%) et recalculer. Si l'EV tombe < +3% avec cette correction → SKIP par défaut.

---

## ÉTAPE 5quater — LOI DU 2-WAY (SCAN OBLIGATOIRE TOUS MARCHÉS)

> ⚠️ **NOUVELLE RÈGLE v5.5 — NON NÉGOCIABLE.**
> Avant de finaliser une recommandation, scanner TOUS les marchés 2-way disponibles sur le match, pas seulement les plus évidents (Over/Under, BTTS).
> Un marché 2-way offre mécaniquement une meilleure valeur car la marge bookmaker est répartie sur 2 issues au lieu de 3.

### Pourquoi cette règle ?
Le réflexe naturel est de regarder Over 2.5, BTTS, victoire. Mais il existe des dizaines de marchés 2-way sous-exploités que les bookmakers pricent moins efficacement car ils attirent moins de volume. Ce sont ces marchés qu'il faut CHERCHER.

### Liste complète des marchés 2-way à scanner systématiquement :

**📊 Marchés de buts (timing)**
- Équipe X marque en 1ère mi-temps (Oui/Non)
- Équipe X marque en 2e mi-temps (Oui/Non)
- But marqué en 1ère mi-temps (Oui/Non)
- But marqué en 2e mi-temps (Oui/Non)
- Score à la mi-temps : 0-0 (Oui/Non)
- Équipe X ouvre le score (Oui/Non)

**📊 Marchés totaux alternatifs**
- Over/Under 0.5 buts
- Over/Under 1.5 buts
- Over/Under 2.5 buts
- Over/Under 3.5 buts
- Over/Under 4.5 buts
- Over/Under 1.5 buts en 1ère mi-temps
- Over/Under 0.5 buts en 2e mi-temps

**📊 Marchés joueurs (props 2-way)**
- Joueur X marque (Oui/Non)
- Joueur X cadre un tir (Oui/Non)
- Joueur X tire au but (Oui/Non)
- Joueur X délivre une passe décisive (Oui/Non)

**📊 Marchés résultat mi-temps**
- Équipe X mène à la mi-temps (Oui/Non)
- Match nul à la mi-temps (Oui/Non)
- 1ère mi-temps = résultat identique au résultat final (Oui/Non)

**📊 Marchés défense**
- Équipe X garde sa cage inviolée — Clean Sheet (Oui/Non)
- Équipe X concède en 2e mi-temps (Oui/Non)
- Équipe X concède au moins 2 buts (Oui/Non)

**📊 Marchés asiatiques**
- Asian Handicap -0.5 (quasi 2-way pur)
- Asian Handicap +0.5
- Over/Under asiatique (lignes demi-entier)

### Procédure Loi du 2-Way :
1. **Identifier le signal fort** du match (ex : "Wolves ne marque pas souvent")
2. **Chercher TOUS les marchés 2-way qui capturent ce signal** (ex : Wolves Clean Sheet Non, Liverpool marque en 2e mi-temps, Under 1.5 Wolves buts, Wolves ne marque pas = Liverpool Win to Nil combiné)
3. **Calculer l'EV sur chacun** avec la cote réelle (Étape 5ter)
4. **Recommander le marché avec le meilleur EV** parmi tous les 2-way identifiés, pas forcément le plus évident

### Règle de priorité :
> Si deux marchés ont le même signal sous-jacent, **toujours recommander le marché 2-way le moins populaire** (moins de volume = moins pricé par le bookmaker = potentiellement plus de valeur).

### ⚠️ GARDE-FOUS OBLIGATOIRES AVANT TOUT PARI CORNERS (Règles 26-27-28)

Avant de recommander un Under corners, répondre à ces 3 questions :

**Question 1 — Biais scénario H2H (Règle 26) :**
> Les N derniers H2H utilisés étaient-ils des victoires faciles du favori ? Si oui → ces stats corners ne sont PAS représentatives d'un match serré. Diviser la fiabilité par 2.

**Question 2 — Danger bloc bas (Règle 27) :**
> Cote favorite < 1.60 + adversaire bloc bas + équipe favorite ≥ 6 corners/match en moyenne récente ?
> Si **OUI aux 3** → Under corners = ⭐ max. Ne pas aller au-delà quelles que soient les stats H2H.

**Question 3 — Mode set-pieces actif (Règle 28) :**
> L'équipe qui doit dominer a-t-elle marqué 2+ buts sur corners dans ses 3 derniers matchs ?
> Si oui → elle cherche activement les corners ce match = sa moyenne réelle > moyenne saison → Under corners déconseillé.

> **Si une seule de ces 3 questions déclenche une alerte → recalculer P(Under corners) à la baisse de 10-15 points de % avant tout calcul EV.**

---

## ÉTAPE 6 — SCAN ACTUALITÉS GAME-CHANGER
Après l'analyse initiale, scanner les news fraîches pour :
- Blessure de dernière minute non pricée par le marché
- Retour surprise d'un joueur clé
- Changement tactique annoncé par le coach
- Conditions météo extrêmes annoncées
- Problème de vestiaire / motivation

**Si une info game-changer est trouvée → RECALCULER les λ et les EV**

---

## ÉTAPE 7 — PRÉSENTATION FINALE
Pour chaque bet recommandé, fournir :
1. **Type de marché** (2-way ✅ ou 3-way ⚠️) — TOUJOURS préciser
2. **Pick** (marché exact)
3. **Cote** (fourchette ou exacte si Stake fourni)
4. **EV%** calculé
5. **Confiance** (étoiles)
6. **Mise Kelly¼** (% bankroll)
7. **Score prédit**
8. **Arguments clés** (3-6 points)

### Ordre de présentation obligatoire :
1. D'abord les bets 2-way ⭐⭐⭐⭐⭐ (les plus safe)
2. Puis les bets 2-way ⭐⭐⭐⭐
3. Puis les bets 2-way ⭐⭐⭐
4. En dernier : les bets 1X2 (3-way), toujours avec le warning ⚠️

### Combos recommandés :
- Double sûr = 2 bets 2-way ⭐⭐⭐⭐⭐
- Triple value = 3 bets 2-way ⭐⭐⭐⭐+
- Fun bet = combo risqué (peut inclure du 1X2)

### Workflow fast-skip (NOUVEAU v5.3) :
> Si un match tombe sous ⭐⭐⭐⭐ dès le filtre forme (Étape 2A) ou H2H (Étape 2D) → **résumé rapide en 2-3 lignes maximum + verdict SKIP**. Ne pas développer l'analyse complète. Économiser le temps d'analyse pour les matchs à vrai potentiel.

---

## RÈGLES ABSOLUES
1. **EV < +3% = NE RECOMMANDE PAS** (même si le match est "intéressant")
2. **Bets safe = 2-way UNIQUEMENT** (Over/Under, BTTS, AH, Double Chance)
3. **1X2 plafonné à ⭐⭐⭐ max** avec mise réduite
4. **Toujours vérifier les absences** avant de calculer
5. **Ne jamais ignorer la météo** en match extérieur
6. **Premier leg de playoff = réduire les λ** (gestion)
7. **Si info game-changer → RECALCULER** (ne pas rester sur l'analyse initiale)
8. **Diversifier les marchés** : ne pas tout mettre en 1X2, explorer Under/Over, BTTS, AH, CS
9. **Max 6-8 bets par jour** pour rester sélectif
10. **Documenter les échecs** pour améliorer le modèle
11. **NOUVEAU v5 :** Ne jamais sous-estimer la motivation survie/relégation adverse
12. **NOUVEAU v5 :** Toujours vérifier la forme RÉCENTE (2-3 derniers matchs) en plus de la tendance longue
13. **NOUVEAU v5.1 :** Forme CL/Europe = forme réelle → ne JAMAIS cloisonner les stats domestiques et européennes
14. **NOUVEAU v5.1 :** Si un risque est identifié dans l'audit devil's advocate → TIRER DES CONSÉQUENCES sur le choix de marché. Un risque "identifié mais écarté" qui se réalise = erreur de méthodologie. Quand un risque est sérieux (ex: surperformance xG), le marché recommandé doit être RÉSISTANT à ce scénario (Over/Under, BTTS plutôt que victoire/AH).
15. **NOUVEAU v5.1 :** AH ≥ -0.75 = quasi 3-way déguisé. Préférer les marchés indépendants du résultat (Over/Under, BTTS) pour les bets safe.
16. **NOUVEAU v5.1 :** Rotation "possible" ≠ rotation confirmée. Ne JAMAIS intégrer une absence spéculative dans le modèle.
17. **NOUVEAU v5.2 :** TOUJOURS vérifier le TIER du championnat (Étape 0) AVANT de commencer l'analyse. Tier 2 = ⭐⭐⭐ max + mise Kelly /2. Tier 1.5 = ⭐⭐⭐⭐ max.
18. **NOUVEAU v5.2 :** Un EV > +20% sur un championnat SANS xG (Tier 2) est un RED FLAG = probablement une surestimation. Réduire la confiance d'un cran.
19. **NOUVEAU v5.2 :** Pour les championnats Tier 1.5 (Eredivisie, Liga Portugal, MLS, J-League), TOUJOURS consulter les sources spécialisées listées dans l'Étape 2 section E bis. Ne pas se fier uniquement aux sources généralistes.
20. **NOUVEAU v5.2 :** MLS en début de saison (Opening Day → ~Matchday 5) = confiance max ⭐⭐⭐. Pas assez de data en cours pour calibrer le Poisson. Se baser sur saison N-1 + transferts.
21. **NOUVEAU v5.3 :** K-League, A-League et Liga BetPlay = Tier 2 (⭐⭐⭐ max, Kelly /2). J-League = Tier 1.5 (⭐⭐⭐⭐ max) grâce à football-lab.jp et sporteria.jp. Toujours consulter les sources spécialisées listées dans l'Étape 2 section E ter avant toute analyse de ces ligues.
22. **NOUVEAU v5.3 :** Ligues exotiques — règle data minimum : si les données forme 5 matchs + H2H au stade + absences ne sont PAS disponibles → **NE PAS PARIER**. Data insuffisante = risque non quantifiable. Rounds 1-3 de toute saison exotique = SKIP systématique.
23. **NOUVEAU v5.3 :** Fast-skip workflow : si un match tombe sous ⭐⭐⭐⭐ dès le filtre forme (Étape 2A) ou H2H (Étape 2D) → résumé rapide 2-3 lignes + verdict SKIP. Ne pas développer l'analyse complète. Économiser le temps pour les matchs à vrai potentiel.
24. **NOUVEAU v5.4 :** Vérification cotes réelles OBLIGATOIRE (Étape 5ter) avant toute recommandation. Scanner Winamax / Betclic / Oddschecker. Recalculer l'EV avec la cote réelle. Si EV réel < +3% → SKIP automatique. Une cote estimée ≠ une cote réelle.
25. **NOUVEAU v5.5 :** LOI DU 2-WAY (Étape 5quater) — Jamais se limiter aux marchés 2-way évidents (Over/Under, BTTS). Scanner TOUS les marchés 2-way disponibles : mi-temps, props joueurs, totaux alternatifs, clean sheet, ouvre le score, etc. Le meilleur EV se trouve souvent sur un marché moins populaire = moins pricé par le bookmaker.

26. **NOUVEAU v5.6 — BIAIS DE SCÉNARIO H2H CORNERS/TOTAUX :** Avant d'utiliser des stats H2H corners ou totaux buts, vérifier dans quel scénario ces matchs se sont joués. Si les N derniers H2H sont majoritairement des victoires faciles de l'équipe favorite → les stats corners/buts reflètent un scénario de domination confortable, PAS un match serré. **Règle absolue : stat H2H corners = valide UNIQUEMENT si le scénario historique correspond au scénario attendu.** Si les H2H étaient des victoires faciles et qu'on anticipe un match difficile pour le favori → diviser la fiabilité de la stat H2H par 2 et abaisser la probabilité d'1 cran.

27. **NOUVEAU v5.6 — DANGER UNDER CORNERS : bloc bas vs équipe forte frustrée :** Un pari Under corners est INTERDIT (⭐ max) si ces 3 conditions sont réunies simultanément : (1) équipe favorite avec cote < 1.60, (2) adversaire joue en bloc bas défensif, (3) équipe favorite a généré en moyenne 6+ corners lors de ses 5 derniers matchs. Quand une équipe forte est bloquée défensivement, elle sature les côtés et génère des corners mécaniquement. Le score reste bas MAIS les corners explosent. **Ce scénario = Under corners = pari toxique.**

28. **NOUVEAU v5.6 — FORME SET-PIECES ACTIVE :** Avant tout pari corners ou totaux buts, vérifier si une équipe est en mode set-pieces actif. Si une équipe a marqué 2+ buts sur corners ou coups de pied arrêtés lors de ses 3 derniers matchs → elle cherche activement les corners = sa moyenne réelle de corners générés dépasse la moyenne de saison. **Exemple d'application :** Liverpool avait marqué 3 buts sur corners vs West Ham → mode set-pieces actif → Under corners automatiquement déconseillé pour ce match, même si la stat saison plaidait pour l'Under.

29. **NOUVEAU v5.9 — RETOURS DE SUSPENSION/BLESSURE = SCAN OBLIGATOIRE :** Avant toute analyse, scanner non seulement les ABSENCES mais aussi les **RETOURS de joueurs clés** (suspension purgée, retour de blessure). Un joueur-clé qui revient = **+0.10-0.20 λ** si c'est le meilleur buteur/passeur de l'équipe. Cumuler avec la règle "joueur série chaude" (v5.1) si applicable. **Le retour d'un buteur en série ≥ 3 matchs buteur = TOUJOURS impacter le λ, même si l'environnement paraît défensif.** Concrètement : dans l'Étape 2 (absences), ajouter une sous-section "RETOURS" qui liste les joueurs qui reviennent et évalue leur impact sur les lambdas. **Exemple d'application :** Tijuana-Santos (09/03/2026) — Lucas Di Yorio (meilleur buteur Santos, 6 matchs buteur consécutifs) revenait de suspension. Non détecté → λ_Santos sous-estimé de 0.15-0.20 → BTTS Non surévalué.

30. **NOUVEAU v5.9 — PLAFOND STREAK : AJUSTEMENT λ MAX ±0.15 SUR BASE D'UNE STREAK SEULE :** Une streak récente (Under, BTTS Non, nuls consécutifs...) est un **signal descriptif**, PAS une loi structurelle. Les ajustements λ basés uniquement sur des streaks sont plafonnés à **±0.15 maximum.** Au-delà, on tombe dans le biais de confirmation. Si une streak dure plus de 5 matchs, augmenter le scepticisme, pas la confiance — car plus elle dure, plus la probabilité de rupture augmente (régression vers la moyenne). **INTERDIT de cumuler un ajustement streak > -0.15 avec un ajustement forme > -0.15 dans le même sens.** Toujours vérifier si la streak est soutenue par les xG (structurelle) ou si elle repose sur de la sous-performance offensive (fragile). Une équipe qui tire 15+ fois par match mais ne marque pas = streak fragile, pas profil défensif. **Exemple d'application :** Tijuana avait 7x Under 1.5 dom d'affilée, mais tirait en moyenne 12+ fois/match → la streak reposait sur de l'inefficacité offensive (fragile), pas sur un choix tactique défensif (structurel). L'ajustement de -0.30 λ était excessif → aurait dû être -0.15 max.

31. **NOUVEAU v5.9 — SIGNAUX CONTRADICTOIRES FORTS = PLAFOND ⭐⭐⭐ OBLIGATOIRE :** Quand deux tendances statistiques fortes (≥5 matchs chacune) se contredisent directement sur le MÊME marché (ex: "Équipe A BTTS Non 8/8 dom" vs "Équipe B BTTS Oui 6/7 ext"), la confiance est **automatiquement plafonnée à ⭐⭐⭐**, quel que soit le Tier ou le marché. Le modèle NE PEUT PAS trancher entre deux signaux de force comparable avec certitude. Réduire la mise Kelly de 50%. **L'absence de consensus statistique = incertitude = pas un bet safe, même si l'EV calculé paraît élevé.** Dans ces cas, privilégier un marché sur lequel les deux signaux convergent plutôt qu'un marché où ils divergent. **Exemple d'application :** Tijuana-Santos (09/03/2026) — "Tijuana BTTS Non 8/8 dom" vs "Santos marque dans 9/10 matchs" = signaux contradictoires forts → BTTS Non aurait dû être plafonné à ⭐⭐⭐ au lieu de ⭐⭐⭐⭐.

32. **NOUVEAU v6.0 — HERITAGE FACTOR CL : JAMAIS PARIER CONTRE UN CLUB HISTORIQUE CL CHEZ LUI EN KNOCKOUT.** Les clubs avec ≥3 titres en Ligue des Champions ou ≥5 demi-finales sur les 15 dernières saisons (Real Madrid, AC Milan, Bayern Munich, Liverpool, FC Barcelona) bénéficient d'un "heritage factor" en phase knockout à domicile qui **transcende les modèles statistiques**. Ces clubs trouvent des ressources émotionnelles, tactiques et collectives dans leur stade en CL knockout que les stats de saison régulière ne capturent pas. **Règle absolue : JAMAIS parier sur la VICTOIRE de l'adversaire en 1X2, ni sur un Asian Handicap négatif pour l'adversaire (AH -0.5 ou plus), contre ces clubs chez eux en CL knockout, quelle que soit la situation d'effectif ou de forme.** Si le modèle statistique dit "l'adversaire gagne à l'extérieur", la recommandation 1X2/AH est automatiquement rétrogradée à **SKIP**. Les seuls marchés autorisés contre ces clubs dans cette configuration sont : Over/Under buts, BTTS, totaux de corners, props joueurs — c'est-à-dire des marchés qui ne dépendent PAS du résultat final du match. **Le heritage factor s'applique UNIQUEMENT en CL knockout (pas en phase de groupes/ligue, pas en Europa League, pas en championnat domestique).** Même si le club historique est décimé par les blessures, même s'il a un nouvel entraîneur inexpérimenté, même si l'adversaire l'a battu au match aller → le Bernabéu, l'Allianz Arena, Anfield, le Camp Nou et San Siro en CL knockout = facteur X non modélisable. **Corollaire :** quand un risque "heritage factor" est IDENTIFIÉ dans l'analyse mais que le modèle recommande quand même de parier contre → c'est un RED FLAG. Si on identifie le risque, on doit AGIR dessus, pas l'écarter. Un risque identifié mais ignoré qui se réalise = erreur de méthodologie (même leçon que Lens-Monaco, Règle 14). **Exemple d'application :** Real Madrid 3-0 Man City (11/03/2026) — Real sans Mbappé, Bellingham et Rodrygo. Toutes les stats pointaient vers City (forme 11 matchs sans défaite, a déjà gagné 2-1 au Bernabéu cette saison, effectif quasi-complet). Le modèle donnait City victoire à 53%. Résultat : Real a écrasé City 3-0. Le Bernabéu en CL knockout, même avec une équipe B en attaque, reste un environnement où les stats ne s'appliquent pas normalement. Vinicius Jr a transcendé en mode héroïque quand toute la pression reposait sur lui. Si on avait appliqué la Règle 32, on aurait SKIP le 1X2 City et gardé uniquement l'Over 2.5 (qui a gagné à 3-0).

---

## ÉCHECS DOCUMENTÉS (pour ne pas répéter les erreurs)

### ❌ Cagliari 0-2 Lecce (16/02/2026)
- **Bet perdant :** Cagliari victoire @2.15 — ⭐⭐⭐⭐⭐ (ERREUR : 1X2 en 5 étoiles)
- **Bet gagnant qu'on aurait pu prendre :** BTTS Non @1.67 ✅ (2-way, proba 66%)
- **Leçons :**
  - Ne JAMAIS mettre un 1X2 en 5 étoiles
  - Peser la forme récente autant que la tendance longue
  - Motivation relégation = facteur réel
  - Un absent (Banda) ≠ équipe neutralisée

### ❌ Lens 2-2 Monaco (21/02/2026)
- **Bet perdant :** Lens -0.75 AH @1.85 — ⭐⭐⭐⭐⭐ (ERREUR : AH trop agressif + risques identifiés mais ignorés)
- **Bets gagnants qu'on aurait pu prendre :** Over 2.5 @1.45 ✅ (4 buts au total), BTTS Oui @2.10 ✅ (les deux équipes ont marqué)
- **Ce qui s'est passé :** Lens mène 2-0 (Édouard 3', Thauvin 56'), tout va bien. Puis Balogun (62') et Zakaria (70') égalisent pour Monaco. L'analyse était JUSTE (Lens domine), mais le MARCHÉ était MAUVAIS (exposé au scénario de remontée).
- **Leçons :**
  - **Forme CL ≠ stats domestiques :** Monaco avait "0 but en 4 déplacements L1" MAIS venait de marquer 2 buts vs PSG en CL mardi. On a cloisonné les stats → erreur.
  - **Joueur série chaude :** Balogun (2 buts vs PSG mardi) a ENCORE marqué. Quand un attaquant est chaud, il transcende le système. +0.15 λ obligatoire.
  - **Rotation "possible" ≠ confirmée :** Pocognoli avait dit "Zakaria pourrait être ménagé". Zakaria a joué ET a marqué. Ne JAMAIS intégrer une absence non confirmée.
  - **Audit identifie risque → doit impacter le marché :** L'overperformance xG de Lens (+11.5 xPTS) avait été identifiée comme "Risque n°1 le plus sérieux" dans l'audit. Mais on l'a écarté sans en tirer de conséquences sur le choix de marché. Si un risque sérieux pointe vers "Lens pourrait se faire rattraper", il faut recommander un marché RÉSISTANT à ce scénario (Over/Under, BTTS), pas un marché qui DÉPEND de la victoire (AH -0.75).
  - **AH ≥ -0.75 = quasi 3-way :** Le AH -0.75 exigeait une victoire d'au moins 1 but. C'est presque un 1X2 déguisé. Pour un bet "ultra safe", Over 2.5 ou BTTS auraient été plus résistants.

### ❌ Hanovre 0-0 Dresde (22/02/2026)
- **Bet perdant :** BTTS Oui @1.72 — ⭐⭐⭐⭐⭐ (ERREUR : 5 étoiles sur un championnat TIER 2 sans xG)
- **EV calculé :** +28.5% (FAUX — surestimation massive due à l'absence de xG)
- **Ce qui s'est passé :** 0-0 malgré Hanovre 4 victoires suite et Dresde BTTS 82% en déplacement. Match fermé, aucune intensité offensive.
- **Leçons :**
  - **2. Bundesliga = PAS de xG sur FBref/Understat** → Le Poisson était calibré sur des buts réels BRUTS, pas sur la qualité des occasions. Les λ étaient artificiellement gonflés par une série chaude.
  - **EV > +20% sur un Tier 2 = RED FLAG, pas GREEN FLAG :** Plus l'EV paraît élevé sans xG pour le valider, plus il faut se méfier. C'est probablement une surestimation du modèle.
  - **Stats brutes flatteuses ≠ réalité :** "BTTS 100% dom" et "BTTS 82% ext" paraissaient écrasantes MAIS sans xG pour vérifier la qualité sous-jacente, ces chiffres sont trompeurs. Une série chaude peut s'arrêter à tout moment.
  - **JAMAIS ⭐⭐⭐⭐⭐ sur un championnat sans xG :** C'est maintenant une RÈGLE ABSOLUE. Le plafond est ⭐⭐⭐ sur un Tier 2, ⭐⭐⭐⭐ sur un Tier 1.5.
  - **Le modèle a été TROP confiant :** 74.7% de proba BTTS → en réalité, sans xG, cette estimation pouvait facilement être 55-60%. La marge d'erreur sur un Tier 2 est ±15%, pas ±5% comme sur un Tier 1.

### ✅ Fiorentina 1-0 Pisa (23/02/2026) — VARIANCE NORMALE
- **Bet perdant :** Over 2.5 @1.74 — ⭐⭐⭐⭐ (CORRECT : bon processus, bonne confiance)
- **Ce qui s'est passé :** Kean marque à la 13', Fiorentina gère en 5-3-2. GK Nicolas fait 4 arrêts. Dodô dégage sur sa ligne. 22 tirs totaux mais seulement 1 but.
- **Verdict :** Variance normale. P(Over 2.5) = 63.5% → le bet perd ~36% du temps. C'est ce qui s'est passé. Processus correct, pas d'erreur structurelle. On ne touche PAS au prompt.

### ❌ Wolves 1-0 Liverpool — Under 10.5 Corners (03/03/2026)
- **Bet perdant :** Under 10.5 Corners @1.72 — ⭐⭐⭐⭐⭐ (ERREUR STRUCTURELLE : 3 biais non détectés)
- **EV calculé :** +40.7% (FAUX — surestimation par biais de scénario H2H)
- **Ce qui s'est passé :** Liverpool n'a pas marqué de tout le match, Wolves a défendu en bloc bas. Liverpool a saturé la surface et généré de nombreux corners. Rodrigo Gomes marque en fin de match sur contre. Score final 1-0 Wolves.
- **Erreur #1 — Biais scénario H2H :** Les 12/14 H2H Under 10.5 corners provenaient de **victoires faciles** de Liverpool (2-1, 3-0, 4-1). Liverpool dominant confortablement n'a pas besoin de multiplier les corners. Ces stats ne représentaient PAS un scénario où Liverpool peine à marquer face à un bloc bas.
- **Erreur #2 — Stat ignorée :** Liverpool avait obtenu **33 corners en 5 matchs** (6.6/match). Cette stat était disponible avant le match et n'a pas été pondérée suffisamment.
- **Erreur #3 — Mode set-pieces actif ignoré :** Liverpool avait marqué **3 buts sur corners vs West Ham**. Une équipe en mode set-pieces actif cherche activement les corners = sa moyenne réelle dépasse la moyenne de saison.
- **Règle manquante :** Si équipe favorite < 1.60 + adversaire bloc bas + 6+ corners/match récents → Under corners = INTERDIT.
- **Bet gagnant qu'on aurait pu prendre :** BTTS Non @~1.75 (Liverpool n'a pas marqué, seul Wolves a scoré) ✅
- **Leçons intégrées :** Règles 26, 27, 28 ajoutées au prompt v5.6.

### ❌ Tijuana 1-2 Santos Laguna (09/03/2026)
- **Bets perdants :** BTTS Non @2.10 — ⭐⭐⭐⭐ (ERREUR : signaux contradictoires non détectés + retour joueur clé ignoré + surpondération streak)
- **EV calculé :** +36.5% (FAUX — surestimation par 3 biais cumulés)
- **Aussi perdants :** Under 2.5 @2.70 ⭐⭐⭐⭐ et Under 1.5 @3.40 ⭐⭐⭐
- **Ce qui s'est passé :** Santos domine offensivement (9 tirs cadrés vs 3 pour Tijuana, 17 tentatives vs 16). Tijuana réduit à 10 (carton rouge). Santos gagne 2-1, Tijuana réduit le score en fin de match (Alejandro Gómez). Le gardien de Tijuana fait 7 arrêts = Santos a pilonné.
- **Erreur #1 — Retour de suspension Di Yorio non détecté :** Lucas Di Yorio, meilleur buteur de Santos (4 buts en saison, 6 matchs buteur consécutifs), revenait de suspension (carton rouge vs Querétaro). Notre scan des absences a vérifié les ABSENTS mais PAS les RETOURS. Di Yorio de retour + en série chaude = λ_Santos aurait dû être augmenté de +0.15-0.20 (règle joueur série chaude v5.1). C'est exactement l'erreur Balogun de Lens-Monaco, répétée.
- **Erreur #2 — Surpondération des streaks domicile :** Tijuana avait 7x Under 1.5 dom, 8x BTTS Non dom. On a ajusté λ de -0.30 (profil "ultra-défensif"). Mais Tijuana tirait 12+ fois/match = la streak reposait sur de l'INEFFICACITÉ offensive, pas sur un choix tactique défensif. C'était une série fragile destinée à casser. L'ajustement maximal aurait dû être -0.15.
- **Erreur #3 — Signaux contradictoires forts ignorés :** "Tijuana BTTS Non 8/8 dom" vs "Santos marque dans 9/10 matchs + BTTS dans 6/7 ext" = contradiction frontale. Au lieu de choisir un camp, il fallait reconnaître l'INCERTITUDE et plafonner la confiance à ⭐⭐⭐.
- **Part de variance :** Le carton rouge de Tijuana (~25% de responsabilité) est imprévisible. Mais 75% de l'erreur est structurelle.
- **Leçons intégrées :** Règles 29 (scan retours de suspension), 30 (plafond streak ±0.15 λ), 31 (signaux contradictoires = ⭐⭐⭐ max) ajoutées au prompt v5.9.

### ❌ Real Madrid 3-0 Manchester City (11/03/2026)
- **Bet perdant :** Man City victoire @2.00 — ⭐⭐⭐⭐ (ERREUR STRUCTURELLE : heritage factor CL ignoré)
- **Aussi perdant :** Man City Over 1.5 buts @2.50 — ⭐⭐⭐⭐ (City n'a marqué aucun but)
- **Bet GAGNANT du même match :** Over 2.5 @1.85 ✅ (3 buts)
- **Ce qui s'est passé :** Real Madrid, sans Mbappé (genou), Bellingham (cuisse), Rodrygo (ACL opéré), Militão, Ceballos et Alaba, a écrasé Man City 3-0 au Bernabéu. Vinicius Jr et l'ensemble de l'équipe ont transcendé dans un match knockout CL à domicile. City, pourtant invaincu en 11 matchs et ayant gagné 2-1 au Bernabéu plus tôt dans la saison, a été dominé.
- **Erreur #1 — Heritage Factor non pricé :** Le Bernabéu en CL knockout est un facteur X qui transcende les modèles statistiques. Real Madrid n'avait pas perdu de match CL knockout à domicile sous cette dynamique émotionnelle depuis des années. On avait IDENTIFIÉ le risque ("Real au Bernabéu en CL = jamais prévisible") mais on a quand même misé CONTRE → erreur de méthodologie (même pattern que Lens-Monaco Règle 14 : risque identifié mais ignoré).
- **Erreur #2 — Biais "effectif décimé = équipe faible" :** On a assimilé "sans Mbappé/Bellingham/Rodrygo" à "Real ne peut pas gagner." En réalité, quand toute la pression repose sur un seul joueur de classe mondiale (Vinicius), ce joueur peut exploser et transcender. Le concept "dead team bounce" s'applique aussi aux stars solitaires héroïques.
- **Erreur #3 — Surpondération du H2H saison :** City avait gagné 2-1 au Bernabéu en phase de groupes cette saison. Mais un match de phase de groupes en décembre ≠ un match de knockout en mars. Le contexte émotionnel, la pression, l'intensité sont incomparables (biais de scénario H2H, Règle 26).
- **Part de variance :** ~30%. Real à 3-0 est un score extrême. Mais la direction (Real gagne) était prévisible si on avait respecté le heritage factor.
- **Leçons intégrées :** Règle 32 (Heritage Factor CL — jamais parier CONTRE un club historique CL chez lui en knockout) ajoutée au prompt v6.0.

---

## CHANGELOG
- **v1** : Modèle de base (stats + Poisson + EV)
- **v2** : Ajout météo, H2H patterns, Kelly
- **v3** : Ajout penalties, arbitres, rotation, xG estimé, absences détaillées
- **v4** (17/02/2026) : Ajout scan sources avancées (Twitter journalistes, xG réels FBref/Understat, arbitres désignés, Reddit r/soccer + r/SoccerBetting), calibration λ par xG réels
- **v5** (21/02/2026) : **Refonte marchés safe = 2-way uniquement.** 1X2 plafonné à ⭐⭐⭐ max. Ajout section "Échecs documentés" avec cas Cagliari-Lecce. Ajout facteur motivation relégation/survie. Retrait étape Card visuelle (gérée séparément).
- **v5.1** (21/02/2026 soir) : **Post-mortem Lens-Monaco.** Ajout règle "Forme CL = forme réelle". Ajout règle "Joueur série chaude" (+0.10-0.15 λ). Ajout règle "Rotation possible ≠ confirmée". Ajout règle "Audit risque → conséquences sur choix de marché". Ajout warning "AH ≥ -0.75 = quasi 3-way déguisé". Ajout cas Lens 2-2 Monaco.
- **v5.2** (22/02/2026) : **Post-mortem Hanovre 0-0 Dresde.** Ajout ÉTAPE 0 — Tier System (Tier 1 / 1.5 / 2) avec plafonds de confiance. Tier 2 = ⭐⭐⭐ max + Kelly /2. Tier 1.5 = ⭐⭐⭐⭐ max. Sources spécialisées Tier 1.5. Règle "EV > +20% sur Tier 2 = RED FLAG". Règle "MLS Opening Day = ⭐⭐⭐ max". Ajout cas Hanovre-Dresde.
- **v5.3** (28/02/2026) : **Ajout ligues exotiques au Tier System.** K-League et A-League → Tier 2 (⭐⭐⭐ max). J-League → Tier 1.5 (⭐⭐⭐⭐ max, xG dispo sur football-lab.jp). Liga BetPlay → Tier 2 (⭐⭐ max). Ajout section E ter avec sources détaillées pour K-League, J-League, A-League, Liga MX, Liga BetPlay. Ajout règle 21 (Tier placement nouvelles ligues), règle 22 (data minimum ligues exotiques — rounds 1-3 = SKIP), règle 23 (fast-skip workflow sous 4 étoiles). Sources globales consolidées dans Étape 2 section E.
- **v5.5** (03/03/2026) : **Ajout LOI DU 2-WAY — Étape 5quater.** Scan obligatoire de TOUS les marchés 2-way disponibles avant recommandation finale. Règle de priorité : marché moins populaire = moins pricé = plus de valeur potentielle. Ajout règle 25.
- **v5.8** (04/03/2026) : **Refonte complète section Liga MX.** Stats : FootyStats (meilleure source xG Liga MX), FotMob, FBref, Sofascore, Dimers, xGscore, BetExplorer. Actualité : Medio Tiempo, RÉCORD, ESPN Deportes MX, TUDN, VivaLigaMX. Insiders Twitter : @mexicoworldcup (Tom Marshall ESPN — référence #1 EN), @ESPNmx, @TUDN, @LigaBBVAMX, @cesarhfutbol, @NayibMoran, @BetLigaMx. Workflow recommandé en 5 étapes pour Liga MX ajouté. Note : l'ancien handle @TomMarshall remplacé par le correct @mexicoworldcup. Premier League (Ornstein, Matt Law, WhoScored...), La Liga (Bouhafsi, DiMarzio, LaLigaEN...), Serie A (DiMarzio, Sky Sport Italia...), Bundesliga (Bundesliga_EN, kicker...), Ligue 1 (RMC, L'Équipe, Telefoot...), Champions League (OptaJoe, UEFA...). Règle ajoutée : scanner AU MOINS un compte insider avant toute analyse Tier 1 pour détecter une blessure non pricée. Ajout 3 règles anti-corners piégeux. Règle 26 : biais scénario H2H (stat H2H corners invalide si les H2H étaient des victoires faciles). Règle 27 : Under corners interdit si équipe forte < 1.60 + bloc bas adversaire + ≥ 6 corners/match récents. Règle 28 : forme set-pieces active (2+ buts sur corners en 3 derniers matchs = moyenne réelle > moyenne saison → Under corners déconseillé). Ajout garde-fous obligatoires dans Étape 5quater. Ajout cas Wolves-Liverpool dans Échecs documentés.
- **v5.9** (09/03/2026) : **Post-mortem Tijuana 1-2 Santos Laguna.** Trois nouvelles règles ajoutées. Règle 29 : scan obligatoire des RETOURS de joueurs clés (suspension purgée, retour blessure) en plus des absences — un buteur en série chaude qui revient = +0.10-0.20 λ obligatoire (même erreur que Balogun/Lens-Monaco, répétée avec Di Yorio). Règle 30 : plafond streak — ajustement λ maximum ±0.15 sur la base d'une streak seule, interdit de cumuler streak + forme > -0.15 dans le même sens, toujours vérifier si la streak est soutenue par les xG (structurelle) ou par de la sous-performance (fragile). Règle 31 : signaux contradictoires forts (≥5 matchs chacun, même marché, sens opposés) = confiance plafonnée à ⭐⭐⭐ automatiquement + Kelly /2. Ajout cas Tijuana-Santos dans Échecs documentés.
- **v6.0** (11/03/2026) : **Post-mortem UCL 8es de finale.** Real Madrid 3-0 Man City malgré Real sans Mbappé, Bellingham et Rodrygo → le modèle avait recommandé City victoire @2.00 (⭐⭐⭐⭐). Erreur structurelle : le "heritage factor" des clubs historiques en CL knockout à domicile transcende les modèles statistiques. **Règle 32 ajoutée : JAMAIS parier sur la victoire de l'adversaire (1X2 ou AH) contre un club historique CL (≥3 titres ou ≥5 demi-finales récentes : Real Madrid, Bayern, AC Milan, Liverpool, Barça) chez lui en knockout CL, quelle que soit la situation d'effectif.** Seuls marchés autorisés contre ces clubs : Over/Under, BTTS, corners, props joueurs. Le heritage factor s'applique uniquement en CL knockout (pas en phase de groupes, pas en Europa League). Si un risque heritage factor est identifié mais que le modèle recommande quand même de parier contre → SKIP obligatoire sur le 1X2/AH. Ajout cas Real Madrid-Man City dans Échecs documentés. Bilan UCL 8es : 5 bets gagnés / 5 perdus (50%), les bets sur les totaux (Over/Under) ont mieux performé que les bets directionnels (1X2/DC), confirmant la supériorité structurelle des marchés 2-way en CL knockout.
