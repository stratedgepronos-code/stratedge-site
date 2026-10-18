# StratEdge Décision V5 — les 16 cases live

Dans **Statistiques en direct**, créer/garder un seul groupe. Ce sont des colonnes à afficher, pas des critères pour éliminer des matchs. Maximum autorisé : 40 ; sélection proposée : **16**.

| Menu | Cases à cocher | Nombre |
|---|---|---:|
| Statistiques Temps plein | Buts ; Total des tirs ; Tirs cadrés ; Possession ; Cartons jaunes ; Cartons rouges ; Cartons jaunes-rouges ; Fautes ; Tirs bloqués ; Photos à l’intérieur de la boîte ; Substitutions | 11 |
| Statistiques 10 dernières minutes | Total des tirs ; Tirs cadrés ; Buts | 3 |
| Statistiques 5 dernières minutes | Total des tirs ; Tirs cadrés | 2 |

« Photos à l’intérieur de la boîte » est le libellé traduit de tirs dans la surface. Tirs dans la surface et substitutions sont conservés pour la lecture détaillée ; ils n’ajoutent pas de poids prédictif inventé. Aucun indicateur ExG propriétaire n’est interprété comme xG de tirs observés.

Les cotes ne sont pas nécessaires pour cette lecture. Tu peux conserver des colonnes de cotes si tu le souhaites, tant que tu restes sous 40. Les cartes confirmées peuvent être envoyées sur Telegram. Aucun pari n’est placé automatiquement. Les buts et jaunes déjà obtenus ne ferment pas l’observation des suivants.

## Collecteur 5.0.0

Sur StratEdge → Réglages → Copier le code / Télécharger le fichier. Dans Tampermonkey, **remplacer le code du script existant** `SEUIL90 · Packball Live Console`, enregistrer, recharger Packball. Garder un seul script et un seul onglet collecteur actifs. Le nom et le namespace restent identiques pour conserver le token.

Le collecteur lit les libellés des colonnes, pas une position fixe. La première valeur de chaque paire appartient au domicile. Il reconnaît désormais les corners cumulés et 5/10 minutes, les tirs bloqués, les tirs dans la surface et les substitutions. Les cotes et les indicateurs « corners attendus » / « un corner toutes les x minutes » ne sont pas confondus avec les corners observés.

Les fautes 5/10 minutes et les tirs bloqués 10 minutes sont calculés par différence de relevés cumulés. Il faut une collecte continue et un point de départ assez ancien ; les premiers relevés affichent honnêtement « fenêtre incomplète ». Les comparaisons sont réinitialisées après changement de score et ne traversent pas la pause. Une donnée manquante ne devient jamais zéro.

PC allumé, navigateur ouvert et Packball sur le groupe live. Les lignes hors écran sont lues si elles sont présentes dans le DOM ; un match non chargé ou virtualisé n’est pas accessible. Vérifier le nombre de lignes reçues. Après 100 secondes sans données fraîches, la mise en évidence disparaît.

## Avant-match

**Ne pas modifier les deux groupes CSV GPT / GPT - 2 actuels.** Leur dictionnaire est conservé, leurs colonnes génériques imposent l’ordre déjà vérifié. L’import ne suppose pas qu’une nouvelle colonne serait un corner.

Le prompt du 18 octobre ajoute `card_references` (global, domicile, extérieur, H2H) et `goal_timing` (buts marqués/encaissés par période et localisation) avec leurs sources. L’arbitre reste dans `referee`. Réimporter un nouveau dossier avant le coup d’envoi pour ces compléments. Ne pas modifier l’ordre des CSV.

## Lecture

Une ligne par match, buts et jaunes ; chaque carte est divisée domicile / visiteurs. Les cases corners du groupe live sont facultatives et ignorées par le radar ; tu peux les décocher sans changer le collecteur. La moyenne FT reste une moyenne de match entier, jamais une quantité « due à cette minute ». « À regarder » demande deux relevés distincts concordants espacés de 20 à 100 secondes. C’est un critère descriptif expérimental, pas une probabilité calibrée. Le bouton Comprendre affiche les faits, réserves et critères exacts.
