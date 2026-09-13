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

## Connexion FootyStats — version 2.1

Le Lab réutilise la constante ou variable serveur `FOOTYSTATS_API_KEY` depuis `public_html/config-keys.php`. Priorité : `STRATEDGE_LAB_FOOTYSTATS_KEY`, environnement `FOOTYSTATS_API_KEY`, `footystats_api_key` dans `config.local.php`, puis constante existante. Aucune clé en JavaScript, CSV, HTML, URL du navigateur ou archive. Le bouton « Tester la connexion FootyStats » est réservé au super administrateur et protégé par CSRF. Le diagnostic de déploiement utilise la même configuration et affiche uniquement le résultat du contrôle.

L’import appelle directement `https://api.football-data-api.com`, sans modifier les anciens proxies ou le flux Telegram. Il rapproche les deux noms orientés domicile/extérieur et l’horaire (tolérance 15 minutes) avec `/todays-matches`, puis utilise `competition_id` comme `season_id` pour `/league-teams?include=stats`. Les pages sont parcourues intégralement. Les noms sont normalisés de façon conservatrice ; les alias explicites se configurent dans `footystats_team_aliases`. Une correspondance absente, multiple, reportée ou déjà commencée ne produit aucun pari. Les ligues doivent être sélectionnées dans le compte FootyStats.

Seuls les bilans `home` du recevant et `away` du visiteur remplacent les six agrégats de buts/échantillons du modèle. Les statistiques de saison sont distinctes des dix derniers matchs généraux PackBall. `seasonGoalsTotal` contient les buts marqués **et** encaissés : la moyenne marquée est donc `(seasonGoalsTotal - seasonConcededNum) / seasonMatchesPlayed`. Les valeurs absentes ou négatives restent inconnues. La référence de ligue utilise les totaux et les échantillons globaux de toutes les équipes disponibles dans la pagination complète. Les xG sont descriptifs ; aucun coefficient prédictif n’est ajouté.

`max_time` est fixé avant les coups d’envoi et arrondi au quart d’heure précédent. Les réponses sont mises en cache pendant 15 minutes dans `se_lab_api_cache`, séparées par compte et paramètres. Le module limite chaque import à 40 appels et 45 secondes de budget réseau. Un rapport partiel est enregistré et les matchs non enrichis restent sans sélection ; réimporter profite du cache. Les prévisions précédentes restent immuables, et le suivi sépare `2.1.0-footystats-venue-experimental` des versions antérieures. Le découpage historique dépend du respect de `max_time` par le fournisseur ; il ne constitue pas une validation de backtest.

Le nouveau profil `packball-custom-gpt-28-20260914` conserve les 28 en-têtes ordonnés. Colonnes à partir de 1 : Over 1,5/2,5/3,5 = 10/11/12 ; Under 1,5/2,5/3,5 = 13/14/15 ; paires matchs/marqués/encaissés = 16/17/18 ; fréquence Over 2,5 = 21. Les colonnes de tirs 25/26 sont conservées brutes, car leurs sous-types ne sont pas confirmés. Le profil 46 colonnes reste accepté, y compris pour le règlement FT.

Validation : `php tests/football-lab/run.php` inclut les réponses API simulées, le schéma 28 colonnes, les vrais dénominateurs par lieu, le changement de date UTC, la pagination, l’archivage sans clé, le cache, les ambiguïtés, l’abstention sans données et le rendu de la fiche. `php ops/check-lab-footystats.php` vérifie séparément l’accès réel du serveur. Une réussite des tests logiciels ne démontre aucune rentabilité.

Documentation fournisseur : [matchs par date](https://footystats.org/api/documentations/todays-matches-matches-by-day), [équipes et statistiques de saison](https://footystats.org/api/documentations/league-teams), [définition des statistiques](https://footystats.org/api/documentations/team).

### Correctif 2.1.1 — contrat de réponse et refus lisibles

Le diagnostic de production a révélé que `/league-teams` omet `competition_id` dans les objets équipe. La saison reste déterminée par la requête `season_id` issue de la rencontre. Un champ absent est accepté ; un identifiant fourni mais contradictoire ou une équipe dupliquée reste rejeté. Les tests reproduisent maintenant ce schéma réel.

Des correspondances PackBall vers les identifiants FootyStats vérifiés sont bornées par pays. Elles conservent les contrôles de l’adversaire, de l’orientation et de l’horaire ; aucun rapprochement flou n’est effectué. Les rencontres absentes de la couverture API restent indisponibles.

Les listes et les fiches distinguent données manquantes, échantillon insuffisant et critères de prix non atteints. Le nombre de marchés cotés correspond aux prix réellement présents. Cette présentation s’applique également aux anciennes analyses, sans modifier leurs prévisions. Le bouton « Relancer avec FootyStats » reconstruit le CSV archivé et crée une nouvelle analyse, en conservant l’originale. Les matchs déjà commencés restent exclus. La politique de prix et de sélection ne change pas.

### Saisie du score sur les cartes

Chaque carte de la page Analyses propose deux champs domicile/extérieur et un bouton « Enregistrer le score final ». Le score reste visible et peut être corrigé ; chaque correction ajoute un événement sans effacer le précédent. Le contrôleur existant calcule gagné/perdu pour le pari FT sélectionné. Sans pari, le score est conservé avec le statut `no_bet`, exclu des métriques de paris. Les anciens paris par mi-temps se règlent dans leur fiche pour éviter de leur appliquer un score final.

POST, CSRF et propriétaire contrôlés. La saisie FT est bloquée côté serveur avant 90 minutes après le coup d’envoi ; l’utilisateur doit attendre la fin effective du match, hors prolongations. Après enregistrement, retour à la carte de la même analyse.
