# Diagnostic et historiques — méthode 2.2.0

## Décisions et archives

La formule Gamma-Poisson et les seuils ne changent pas : huit rencontres minimum
pour chaque véritable échantillon par lieu, cote 1,60–3,50, probabilité ≥ 50 %,
espérance ≥ 4 %, écart au marché ≥ 2,5 points et espérance non négative dans
les scénarios de sensibilité. Ce modèle reste expérimental, non calibré.

La version `2.2.0-footystats-diagnostics-experimental` identifie les nouvelles
analyses avec provenance conservée, alias vérifiés et historiques descriptifs.
Une consultation ou un export ne modifie jamais une prévision enregistrée.
« Relancer avec FootyStats » crée une autre analyse, à la date de la relance :
elle n'est pas une reproduction des informations disponibles lors du premier import.

## Données insuffisantes et refus de prix

Le diagnostic distingue une correspondance absente, l'API indisponible, des
données incomplètes, un échantillon insuffisant et des critères non atteints
avec données exploitables. Les erreurs de lignes figurent séparément dans
`import_errors`. Tous les marchés conservent leurs raisons, cotes, probabilités
et calculs de prix dans l'export JSON.

Les anciens messages « Échantillon vide ou totaux incomplets » ne permettent
pas de choisir l'une des deux causes : leur catégorie reste explicitement
`sample_or_data_incomplete`. Une vérification ultérieure du fournisseur constitue
une preuve complémentaire, pas une modification de l'archive.

Si le bilan FootyStats échoue, les calculs de secours déjà présents sont marqués
`descriptive_only` et `usable_for_selection=false`. Les effectifs PackBall ne
sont jamais présentés comme des échantillons domicile/extérieur FootyStats.

## Sources alternatives : contexte descriptif 1.0

1. Terminer l'enrichissement principal de **tous** les matchs avant les recherches
   optionnelles. Le budget global reste de 40 requêtes et 45 secondes, avec cache
   de 15 minutes. Une source inaccessible ou incomplètement paginée reste signalée.
2. Pour un échantillon courant inférieur à huit ou inexploitable, utiliser les
   identifiants exacts des clubs. Sans identité vérifiée, aucune association
   automatique d'historique n'est effectuée.
3. Identifier la compétition courante dans `league-list`, puis sa saison
   précédente. Identifier un championnat national par pays du club et liste
   explicite de noms de championnats seniors. Seules les compétitions accessibles
   dans l'abonnement sont interrogées. Les noms inconnus restent non résolus.
   Le pays vient de `league-teams`, ou de `team` si nécessaire.
4. Consulter `league-matches` pour les saisons récentes identifiées (année courante
   et précédente), avec le même `max_time` que le bilan principal. Retenir, par
   source distincte, les **20 derniers matchs terminés au maximum sur 365 jours**,
   à domicile pour le recevant et à l'extérieur pour le visiteur. Exclure les
   terrains neutres, les dates futures et les rencontres commencées moins de
   quatre heures avant l'instant de référence. Dédupliquer par ID ; une
   contradiction d'identifiant, compétition ou score invalide la source.
5. Conserver séparément chaque compétition et saison, ses IDs, les matchs
   utilisés, la fenêtre recherchée, les dates effectivement observées, le lieu,
   l'effectif, les buts marqués/encaissés et la fréquence +2,5. Ne jamais additionner
   les échantillons de niveaux différents. Un changement de division n'est pas
   assimilé à une continuité de niveau.

Ces historiques peuvent soutenir une **présélection descriptive**, pas produire
un prix pour une affiche européenne : une fréquence nationale n'est pas sa
probabilité européenne. Aucun poids ou coefficient de transfert n'est inventé.
L'utilisation future dans la sélection exigerait un modèle de force des équipes
et des compétitions, une validation chronologique hors échantillon, un contrôle
de calibration et une nouvelle version de méthode. La saison précédente ne
remplace donc pas silencieusement la saison actuelle dans le modèle.

Les 365 jours / 20 rencontres définissent une fenêtre descriptive reproductible,
pas un réglage optimisé sur les deux affiches proposées. Les sources restent
visibles même avec moins de huit rencontres et ne créent jamais de pari.

Documentation fournisseur : [League List](https://footystats.org/api/documentations/league-list),
[Team](https://footystats.org/api/documentations/team),
[League Matches](https://footystats.org/api/documentations/match-schedule-and-stats).

## Vérification du 17 septembre 2026

L'archive du 16 septembre à 20:52 UTC contient 19 matchs, 7 bilans enrichis,
12 indisponibles et zéro erreur d'import. Les deux tables CSV ont été récupérées
dans l'import enregistré : fixtures `sept17-gpt-33.csv` et `sept17-gpt2-28.csv`.
Le snapshot sportif `sept17-snapshot.json` conserve les données du fournisseur
de cette archive, sans clé ni identité utilisateur.

Causes archivées : 6 correspondances absentes ; 6 erreurs « vide ou incomplet » ;
5 petits échantillons (4/4, 4/5, 4/4, 2/2, 2/3) ; 2 analyses exploitables
(Botafogo–Grêmio : 13/13, Once Caldas–Tolima : 14/14) rejetées sur les marchés.
Pour Botafogo, notamment, le scénario défavorable suffit à écarter le −2,5 et
le BTTS non, malgré leurs prix centraux.

Une vérification du fournisseur à 21:22 UTC, demandant la coupure originale
20:45 UTC, confirme les six échantillons européens : Levski–Salzburg 0/2,
Sociedad–Bournemouth 0/0, Plzeň–Union 1/0, Palace–Lech 0/2,
Lillestrøm–Torreense 1/0, Beşiktaş–Marseille 3/0 (saison Europa League 17127).
Les totaux de buts nuls des échantillons vides sont effectivement présents.
Cette vérification ne prouve pas que le fournisseur n'a jamais révisé ses données.

Sur le snapshot original, +2,5 Sociedad à 1,72 et Plzeň à 1,79 sont bloqués
uniquement par ce défaut de données : leurs calculs PackBall de secours passent
les critères de prix, sans constituer des estimations par lieu validées.
La reproduction des deux tables et du snapshot conserve zéro sélection.

Les variantes Montréal/Montreal Impact, TSG Hoffenheim/Hoffenheim,
Ferencvárosi/Ferencváros et NEC Nijmegen/NEC ont été vérifiées sur les fixtures
fournisseur, adversaire, horaire et IDs ; les alias sont bornés au pays/périmètre
de l'export. Le rapprochement n'utilise aucune recherche approximative de nom.
Internacional de Bogotá/La Equidad reste à vérifier ; Manchester City–Norwich
ne possède pas de correspondance au créneau demandé dans la réponse consultée.


## Effet vérifié de la correction

Relecture avec le code corrigé le 16 septembre à 21:28 UTC, sur les deux tables
originales et avec des données fournisseur actualisées : 19 matchs, zéro erreur,
zéro sélection. Les correspondances passent de 13 à 17 ; 8 bilans courants sont
calculables, 9 autres ont au moins un échantillon nul. Le diagnostic final donne
15 échantillons insuffisants, 2 refus après analyse et 2 identités encore non
résolues. L'empreinte de l'archive originale est identique avant et après l'essai.

Les historiques descriptifs existent pour 13 matchs, parfois d'un seul côté.
Sociedad : 3 rencontres à domicile en Liga actuelle, 17 en saison précédente
dans la fenêtre ; Bournemouth : 2 à l'extérieur en Premier League actuelle,
17 en saison précédente. Union : 3 à l'extérieur en championnat belge actuel,
11 en saison précédente. Le championnat tchèque de Plzeň n'est pas présent
au catalogue accessible : aucune moyenne domestique n'est inventée.

La limite globale d'appels peut laisser certaines sources complémentaires
incomplètes ; leur échec est affiché, les sources déjà obtenues sont conservées.
Un nouvel import utilise le cache et crée une nouvelle archive datée.
