# StratEdge Décision V5 — les 19 cases live

Dans **Statistiques en direct**, créer/garder un seul groupe. Ce sont des colonnes à afficher, pas des critères pour éliminer des matchs. Maximum autorisé : 40 ; sélection proposée : **19**.

| Menu | Cases à cocher | Nombre |
|---|---|---:|
| Statistiques Temps plein | Buts ; Total des tirs ; Tirs cadrés ; Possession ; Cartons jaunes ; Cartons rouges ; Cartons jaunes-rouges ; Fautes ; Corners ; Tirs bloqués ; Photos à l’intérieur de la boîte ; Substitutions | 12 |
| Statistiques 10 dernières minutes | Total des tirs ; Tirs cadrés ; Corners ; Buts | 4 |
| Statistiques 5 dernières minutes | Total des tirs ; Tirs cadrés ; Corners | 3 |

« Photos à l’intérieur de la boîte » est le libellé traduit de tirs dans la surface. Tirs dans la surface et substitutions sont conservés pour la lecture détaillée ; ils n’ajoutent pas de poids prédictif inventé. Aucun indicateur ExG propriétaire n’est interprété comme xG de tirs observés.

Les cotes ne sont pas nécessaires pour cette lecture. Tu peux conserver des colonnes de cotes si tu le souhaites, tant que tu restes sous 40. Aucun pari ni message Telegram automatique. Les buts, corners et jaunes déjà obtenus ne ferment pas l’observation des suivants.

## Collecteur 5.0.0

Sur StratEdge → Réglages → Copier le code / Télécharger le fichier. Dans Tampermonkey, **remplacer le code du script existant** `SEUIL90 · Packball Live Console`, enregistrer, recharger Packball. Garder un seul script et un seul onglet collecteur actifs. Le nom et le namespace restent identiques pour conserver le token.

Le collecteur lit les libellés des colonnes, pas une position fixe. La première valeur de chaque paire appartient au domicile. Il reconnaît désormais les corners cumulés et 5/10 minutes, les tirs bloqués, les tirs dans la surface et les substitutions. Les cotes et les indicateurs « corners attendus » / « un corner toutes les x minutes » ne sont pas confondus avec les corners observés.

Les fautes 5/10 minutes et les tirs bloqués 10 minutes sont calculés par différence de relevés cumulés. Il faut une collecte continue et un point de départ assez ancien ; les premiers relevés affichent honnêtement « fenêtre incomplète ». Les comparaisons sont réinitialisées après changement de score et ne traversent pas la pause. Une donnée manquante ne devient jamais zéro.

PC allumé, navigateur ouvert et Packball sur le groupe live. Les lignes hors écran sont lues si elles sont présentes dans le DOM ; un match non chargé ou virtualisé n’est pas accessible. Vérifier le nombre de lignes reçues. Après 100 secondes sans données fraîches, la mise en évidence disparaît.

## Avant-match

**Ne pas modifier les deux groupes CSV GPT / GPT - 2 actuels.** Leur dictionnaire est conservé, leurs colonnes génériques imposent l’ordre déjà vérifié. L’import ne suppose pas qu’une nouvelle colonne serait un corner.

Les exports actuels peuvent manquer des moyennes de corners. Le nouveau prompt permet une référence externe par équipe, sourcée et avec taille d’échantillon, via `teams.h/a.corner_reference`. Sinon le tableau affiche « Repère avant-match indisponible », tout en lisant les corners live.

## Lecture

Une ligne par équipe, trois cases : buts, corners, jaunes. La moyenne FT reste une moyenne de match entier, jamais une quantité « due à cette minute ». « À regarder » demande deux relevés distincts concordants espacés de 20 à 100 secondes. C’est un critère descriptif expérimental, pas une probabilité calibrée. Le bouton Comprendre affiche les faits, réserves et critères exacts.
