# StratEdge Lab

Nouveau module administrateur : `/panel-x9k3m/football-lab/import.php`.
Accès limité au super administrateur existant. Toutes les actions utilisent POST et le jeton CSRF du site.

## Fonctionnement

- Un seul CSV PackBall avec statistiques et cotes. UTF-8, maximum 2 Mo et 1 000 lignes. La sélection ou le dépôt déclenche directement l’analyse ; aucun second fichier, mapping ou paramètre n’est demandé. Sans JavaScript, un bouton permet de soumettre le même fichier.
- Profil `packball-custom-gpt-46-v1`, défini à partir du CSV et de la capture fournis le 9 septembre 2026. Vérification des 46 en-têtes ordonnés, séparateur détecté, dates complètes `d-m-Y H:i`, fuseau Europe/Paris. Le nom du fichier ne détermine jamais la date des matchs.
- Colonnes (numérotation à partir de 1) : cotes 12/13 = +/−2,5 ; 14/15 = −/+1,5 (ordre inversé dans cet export) ; 16/17 = +/−3,5 ; 18/19 = BTTS oui/non. Échantillons 20/21 ; buts marqués 22/23 ; buts encaissés 24/25, domicile puis extérieur.
- Les en-têtes répétés restent associés à leur position. Une permutation de deux colonnes portant toutes deux « Odds » ne peut pas être détectée par ces seuls en-têtes : conserver l’ordre du tableau fourni. Une autre structure de colonnes est refusée, sans deviner les marchés.
- Croisement des moyennes de buts puis référence Poisson indépendante. L’import automatique compare les huit marchés du match entier présents dans cet export, uniquement avec une cote exploitable. Les autres statistiques sont conservées dans le tableau source ; cette version du calcul utilise les moyennes de buts et les échantillons, sans transformer les tirs ou les scores HT en moyennes de mi-temps.
- Classement par probabilité. Un choix initial par match ; absence de candidat possible. Cotes affichées comme exportées, jamais prétendues actualisées. L’import enregistre un rapport même si tous les matchs sont déjà commencés, avec les motifs d’exclusion.
- Les anciens imports et analyses restent accessibles dans le stockage. Les anciennes actions POST restent compatibles ; la page d’import n’expose plus leur configuration.
- Signalement des données invalides, collisions, matchs commencés et incohérences entre périodes.
- Fiche de match, contrôle contextuel, décisions et résultats append-only. Les probabilités initiales restent figées.
- Suivi des premiers choix par match dans les 100 dernières analyses : réussite, Brier, log-loss, calibration descriptive et ROI fictif sur les seules cotes connues. Les marchés ne sont pas présentés comme rentables ni calibrés.

## Stockage

Deux nouvelles tables indépendantes sont créées au premier accès authentifié : `se_lab_runs` et `se_lab_events`. Le compte MySQL du site doit disposer de CREATE. Aucune table existante n’est modifiée. La sauvegarde de la base doit inclure ces tables.

## Contexte via recherche web

Configuration optionnelle côté serveur : `STRATEDGE_LAB_OPENAI_KEY` et `STRATEDGE_LAB_OPENAI_MODEL`, ou `public_html/admin/football-lab/config.local.php` suivant l’exemple. Le fichier local est ignoré par Git. Le modèle configuré doit être disponible sur le compte et compatible avec Responses et `web_search`.

La recherche est déclenchée par le super administrateur depuis une fiche ; aucun appel n’est effectué automatiquement à l’import. Chaque appel transmet uniquement les équipes, la compétition et l’horaire. `store: false`, timeout 90 secondes et maximum trois appels d’outil par requête. Les clés et les réponses d’erreur brutes ne sont pas affichées. Sans configuration, le module reste utilisable pour les statistiques et les notes manuelles, avec un avertissement explicite.

Les résultats doivent contenir des citations HTTP(S). Une recherche reste « à relire », n’altère pas la probabilité et ne valide pas automatiquement le contexte. Les sources peuvent manquer, être contradictoires ou incomplètes.

Documentation de l’intégration : https://developers.openai.com/api/docs/guides/tools-web-search

## Validation

`php tests/football-lab/run.php` : imports, formules, monotonie, jointures, doublons, périodes, refus des données invalides, propriété des analyses, immutabilité, règlement et déduplication des métriques. Nécessite PDO SQLite pour la base de test ; la production utilise le MySQL existant.

Le workflow `StratEdge Lab checks` exécute ces tests et vérifie les syntaxes PHP et JavaScript. Les tests utilisent des rencontres synthétiques explicitement fictives ; aucun faux match n’est livré dans les pages.

## Limites initiales

Le module est une référence expérimentale opérationnelle, pas un modèle entraîné ou validé. Aucun export réel actuel n’a encore été fourni pour valider le mapping PackBall. Pas de correction automatique pour force des adversaires, blessures ou dispersion ; pas de cotes live ; pas de règlements automatiques ni de publication aux membres. La recherche web requiert une configuration API valide et sa disponibilité en production doit être contrôlée.
