# StratEdge Lab

Nouveau module administrateur : `/panel-x9k3m/football-lab/import.php`.
Accès limité au super administrateur existant. Toutes les actions utilisent POST et le jeton CSRF du site.

## Fonctionnement

- Un seul CSV PackBall avec statistiques et cotes. UTF-8, maximum 2 Mo et 1 000 lignes. La sélection ou le dépôt déclenche directement l’analyse ; aucun second fichier, mapping ou paramètre n’est demandé. Sans JavaScript, un bouton permet de soumettre le même fichier.
- Profil `packball-custom-gpt-46-v1`, défini à partir du CSV et de la capture fournis le 9 septembre 2026. Vérification des 46 en-têtes ordonnés, séparateur détecté, dates complètes `d-m-Y H:i`, fuseau Europe/Paris. Le nom du fichier ne détermine jamais la date des matchs.
- Colonnes (numérotation à partir de 1) : cotes 12/13 = +/−2,5 ; 14/15 = −/+1,5 (ordre inversé dans cet export) ; 16/17 = +/−3,5 ; 18/19 = BTTS oui/non. Échantillons 20/21 ; buts marqués 22/23 ; buts encaissés 24/25, domicile puis extérieur.
- Les en-têtes répétés restent associés à leur position. Une permutation de deux colonnes portant toutes deux « Odds » ne peut pas être détectée par ces seuls en-têtes : conserver l’ordre du tableau fourni. Une autre structure de colonnes est refusée, sans deviner les marchés.
- Le moteur V2 compare les huit marchés du match entier via une distribution prédictive Gamma-Poisson, puis contrôle les prix et la cohérence des statistiques. Les paramètres et limites sont détaillés ci-dessous. Aucun score HT n’est transformé en moyenne de mi-temps.
- Classement par espérance du scénario le moins favorable parmi les marchés passant les contrôles. Un choix initial par match ; absence de candidat possible. Cotes affichées comme exportées, jamais prétendues actualisées. L’import enregistre un rapport même si tous les matchs sont déjà commencés, avec les motifs d’exclusion.
- Les anciens imports et analyses restent accessibles dans le stockage. Les anciennes actions POST restent compatibles ; la page d’import n’expose plus leur configuration.
- Signalement des données invalides, collisions, matchs commencés et incohérences entre périodes.
- Fiche de match, contrôle contextuel, décisions et résultats append-only. Les probabilités initiales restent figées.
- Suivi des premiers choix par match dans les 100 dernières analyses : réussite, Brier, log-loss, calibration descriptive et ROI fictif sur les seules cotes connues. Les marchés ne sont pas présentés comme rentables ni calibrés.

## Stockage

Deux nouvelles tables indépendantes sont créées au premier accès authentifié : `se_lab_runs` et `se_lab_events`. Le compte MySQL du site doit disposer de CREATE. Aucune table existante n’est modifiée. La sauvegarde de la base doit inclure ces tables.

## Contexte via recherche web

Configuration optionnelle côté serveur : `STRATEDGE_LAB_OPENAI_KEY` et `STRATEDGE_LAB_OPENAI_MODEL`, ou `public_html/admin/football-lab/config.local.php` suivant l’exemple. Le fichier local est ignoré par Git. Le modèle configuré doit être disponible sur le compte et compatible avec Responses et `web_search`.

La recherche est déclenchée par le super administrateur depuis une fiche ; aucun appel n’est effectué automatiquement à l’import. Chaque appel transmet uniquement les équipes, la compétition, l’horaire et le libellé du marché sélectionné. `store: false`, timeout 90 secondes et maximum trois appels d’outil par requête. Les clés et les réponses d’erreur brutes ne sont pas affichées. Sans configuration, le module reste utilisable pour les statistiques et les notes manuelles, avec un avertissement explicite.

Les résultats doivent contenir des citations HTTP(S). Une recherche reste « à relire », n’altère pas la probabilité et ne valide pas automatiquement le contexte. Les sources peuvent manquer, être contradictoires ou incomplètes.

Documentation de l’intégration : https://developers.openai.com/api/docs/guides/tools-web-search

## Validation

`php tests/football-lab/run.php` : imports, formules, monotonie, jointures, doublons, périodes, refus des données invalides, propriété des analyses, immutabilité, règlement et déduplication des métriques. Nécessite PDO SQLite pour la base de test ; la production utilise le MySQL existant.

Le workflow `StratEdge Lab checks` exécute ces tests et vérifie les syntaxes PHP et JavaScript. Les tests utilisent des rencontres synthétiques explicitement fictives ; aucun faux match n’est livré dans les pages.

## Limites initiales

Le module est une référence expérimentale opérationnelle, pas un modèle entraîné ou validé. Le mapping correspond à l’export réel fourni le 9 septembre 2026 et à son ordre de colonnes. Pas de correction automatique pour force des adversaires ou blessures ; pas de cotes live ; pas de règlements automatiques ni de publication aux membres. La recherche web requiert une configuration API valide et sa disponibilité en production doit être contrôlée.

## Moteur V2 : probabilités et sélection

L’import unique appelle désormais `DecisionEngine` après les contrôles de dates et de données. Les archives V1 restent inchangées. Le suivi se filtre par version afin de ne pas attribuer à V2 les anciens résultats.

- Gamma-Poisson : référence de ligue divisée par deux, poids initial équivalent à quatre matchs. Les agrégats attaque/défense sont chacun pondérés à 0,5 pour ne pas traiter deux listes potentiellement dépendantes comme deux fois plus de matchs indépendants. Les moyennes arrondies donnent des pseudo-comptages, pas un historique exact. La distribution prédictive binomiale négative intègre l’incertitude des intensités sous ces hypothèses. Les équipes restent conditionnellement indépendantes ; pas de modèle Dixon-Coles ajusté sans historique détaillé.
- Les probabilités sont indépendantes des cotes. Les cotes servent à comparer le prix, l’espérance et la paire d’issues opposées normalisée proportionnellement. Cette paire exportée n’est pas un consensus de bookmakers efficaces ni une cote live certifiée.
- Critères opérationnels préfixés et expérimentaux : cote 1,60–3,50, probabilité ≥50 %, espérance ≥4 %, écart au marché normalisé ≥2,5 points, espérance ≥0 dans tous les scénarios (intensités ±15 %, référence de ligue de poids 2 et 8). Les scénarios ne sont pas des intervalles de confiance. Le prix minimal affiché ne couvre que les critères d’espérance/sensibilité, pas les autres filtres.
- Les fréquences +2,5/BTTS et le repère croisé CS/FTS bloquent les désaccords de plus de 25 points ; ils ne sont pas ajoutés comme observations indépendantes au calcul de buts. Les tirs sont contrôlés et présentés avec possession/PPG, sans coefficients prédictifs inventés. Les colonnes de tirs suivent les icônes : tirs concédés 35/36, produits 37/38, cadrés produits 39/40 et cadrés concédés 41/42 (indices à partir de 1).
- Aucun pari lorsque les critères échouent. Les pistes restantes sont classées par espérance du scénario le moins favorable. Elles restent « contexte à examiner ». Le contexte sourcé n’ajoute aucun bonus chiffré et ne se substitue pas aux données de composition.
- Après les matchs, le même export PackBall peut être chargé depuis le bloc « Fin de journée » de la page d’import. Les lignes `FT` sont rapprochées par date, équipe domicile et équipe extérieure ; le score final règle le pari de la période entière et met à jour l’historique. Les lignes non terminées restent en attente, les paris par période sont laissés à la saisie manuelle, et une validation déjà enregistrée n’est jamais écrasée. Les doublons ou scores contradictoires sont affichés pour contrôle.
- Cette version n’est ni entraînée ni calibrée sur des résultats indépendants. Ne pas interpréter le passage des tests logiciels comme la preuve d’un meilleur taux de réussite ou d’une rentabilité. Il manque les rencontres détaillées, la force des adversaires, les xG, les actualisations de cotes et les effectifs pour un modèle professionnel complet.

Références mathématiques : [prédiction postérieure, exemple Poisson-Gamma (Stan)](https://mc-stan.org/docs/stan-users-guide/posterior-prediction.html) ; [paramétrage de la binomiale négative (SciPy)](https://docs.scipy.org/doc/scipy/reference/generated/scipy.stats.nbinom.html). Ces références justifient les formules, pas les seuils de sélection football.
