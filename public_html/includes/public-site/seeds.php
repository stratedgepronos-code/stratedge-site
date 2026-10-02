<?php
// Original educational articles. No invented matches, bets, results, endorsements or audience figures.
return [
 ['slug'=>'journee-sans-pari-une-decision','title'=>'Une journée sans pari peut être une bonne décision.','summary'=>'Données manquantes, contexte incertain ou cote insuffisante : trois raisons très différentes de passer son tour. Et trois choses à expliquer.','kind'=>'guide','sport'=>'football','body'=><<<'TEXT'
## Le silence mérite une explication

Une journée d’analyse peut se terminer sans sélection. Ce n’est ni une preuve de prudence à elle seule, ni la preuve que le travail n’a pas été fait. La question utile est plus précise : à quel endroit la décision s’est-elle arrêtée ?

Trois situations doivent rester distinctes. L’analyse peut être impossible faute de données. Elle peut être calculable mais encore trop fragile pour décider. Elle peut enfin être complète et conclure que le prix proposé ne convient pas. Le même message « aucun pari » masque sinon des réalités opposées.

## Quand on ne sait pas encore

Une correspondance d’équipe introuvable, un historique absent ou une compétition qui vient de commencer peuvent empêcher une estimation sérieuse. Dans ce cas, la bonne conclusion porte sur l’état des données, pas sur la qualité sportive du match.

Un historique plus large peut aider à décrire les équipes. Mais changer de championnat, de saison ou de niveau d’opposition change aussi le sens des chiffres. On doit identifier cette source de remplacement et ses limites. Une description peut être utile sans devenir automatiquement une probabilité exploitable.

## Quand le match paraît intéressant, mais pas sa cote

Imaginons un événement évalué à 60 % de chances, avec une estimation incertaine. À la cote de 1,50, son seuil de rentabilité théorique est de 66,7 %. Même si l’événement paraît plus probable que son contraire, ce prix ne suffit pas au regard de cette estimation.

Cet exemple est purement pédagogique. Il n’est ni une cote actuelle, ni une sélection. Il rappelle simplement qu’on n’achète pas une impression de confiance : on accepte une exposition à un prix donné.

## Une absence importante peut changer la lecture

Une composition probable n’est pas une composition officielle. Une absence annoncée sans source fiable ne devient pas un fait. Et un match de coupe entre deux rencontres européennes peut conduire à un calendrier d’effort différent, sans que cela suffise à prédire une rotation.

Ces éléments doivent être vérifiés et datés. Tant que l’information manque, le dossier doit conserver cette incertitude au lieu de la remplacer par une hypothèse présentée comme certaine.

## Que publier quand on ne retient rien ?

Une explication de méthode, un débrief d’hypothèses ou un dossier descriptif peut avoir davantage d’intérêt qu’un pari ajouté pour remplir la journée. Le journal peut rester vivant sans forcer une sélection.

L’objectif est de pouvoir relire une décision et comprendre ce qui était connu à cet instant. Une abstention documentée fait partie de ce travail. Elle ne garantit pas les résultats des décisions suivantes.
TEXT],
 ['slug'=>'domicile-exterieur-bien-lire-les-statistiques','title'=>'Domicile, extérieur : les chiffres ne racontent pas le même match.','summary'=>'Une moyenne globale n’est pas un bilan par lieu. Avant de comparer deux équipes, il faut savoir quels matchs se cachent derrière leurs statistiques.','kind'=>'guide','sport'=>'football','body'=><<<'TEXT'
## Commencer par la bonne question

Lorsqu’un tableau place une équipe à gauche et l’autre à droite, on peut être tenté de lire la première colonne comme un bilan à domicile et la seconde comme un bilan à l’extérieur. Ce placement décrit pourtant souvent seulement l’affiche du prochain match. Il ne renseigne pas à lui seul sur le périmètre des statistiques.

Dans les exports PackBall utilisés par StratEdge, les moyennes globales doivent rester identifiées comme telles. Pour analyser un recevant à domicile et un visiteur à l’extérieur, il faut un historique qui fournisse effectivement cette séparation.

## Un exemple pour voir la différence

Prenons une équipe fictive qui a inscrit 12 buts en 10 matchs. Sa moyenne globale est de 1,2 but. Si elle a marqué 10 de ces buts en 5 rencontres à domicile et 2 en 5 déplacements, les deux moyennes par lieu sont de 2,0 et de 0,4.

Aucun de ces nombres n’est faux. Ils répondent simplement à des questions différentes. La moyenne globale ne permet pas de reconstituer les deux bilans par lieu. Il manque la répartition des rencontres et des buts.

## La taille de l’échantillon fait partie du chiffre

Deux matchs et vingt matchs ne portent pas la même information. Un résultat inhabituel pèse beaucoup plus dans une série courte. Afficher un pourcentage sans son nombre d’observations donne une impression de précision qui peut être trompeuse.

Le nombre de matchs, la période et la compétition doivent donc accompagner les taux. Un pourcentage issu des cinq dernières rencontres décrit ces cinq rencontres. Ce n’est pas directement la probabilité du prochain événement.

## Le piège du début de compétition européenne

Une équipe peut avoir très peu joué dans la compétition européenne courante et déjà disposer de nombreux matchs de championnat. Utiliser le championnat comme contexte peut être pertinent, mais le niveau et le style des adversaires peuvent différer.

La saison précédente peut apporter de la profondeur, tout en introduisant d’autres changements : effectif, entraîneur, rythme de jeu ou niveau de compétition. Additionner ces données sans examen ne crée pas automatiquement un meilleur échantillon.

## Une lecture que l’on peut contrôler

Une présentation utile indique : la source, la saison ou les dates couvertes, la compétition et le nombre de matchs par lieu. Si ces éléments ne sont pas disponibles, la limite doit apparaître dans l’analyse.

L’enrichissement FootyStats apporte des informations complémentaires lorsqu’elles sont disponibles. Il ne dispense pas de contrôler la compétition source et la fraîcheur des données. Une API est un moyen d’accès aux chiffres, pas une garantie que toutes les questions statistiques sont résolues.
TEXT],
 ['slug'=>'taux-reussite-roi-lire-un-bilan','title'=>'Un bon taux de réussite suffit-il à faire un bon bilan ?','summary'=>'Cotes, mises, paris annulés et données manquantes : ce qu’il faut regarder avant de tirer une conclusion d’un pourcentage de réussite.','kind'=>'debrief','sport'=>'multisports','body'=><<<'TEXT'
## Deux chiffres, deux questions

Le taux de réussite répond à une question simple : quelle part des paris gagnés ou perdus a été gagnée ? Le rendement, ou ROI, compare un gain net aux mises engagées. Les deux indicateurs ne sont pas interchangeables.

Un pourcentage de victoires peut être élevé et le bilan négatif si les cotes sont trop basses. À l’inverse, des cotes plus élevées peuvent produire un taux de réussite plus faible, avec des résultats beaucoup plus irréguliers.

## Dix paris fictifs pour comprendre

Imaginons dix paris, avec une mise identique de 1 unité et une cote de 1,40 pour chacun. Sept sont gagnés et trois sont perdus. Le taux de réussite atteint 70 %.

Les sept victoires rapportent chacune 0,40 unité de gain net, soit 2,80 unités. Les trois défaites coûtent 3 unités. Le bilan net est donc de −0,20 unité pour 10 unités misées : le ROI est de −2 %.

Cet exemple est fictif et pédagogique. Il ne représente pas les résultats de StratEdge. Il montre pourquoi un taux de réussite ne doit jamais être lu isolément.

## Pourquoi afficher une mise fixe ?

Une simulation à mise fixe permet de comparer les résultats sans supposer une gestion de bankroll particulière. Dans notre bilan public, chaque pari terminé et non annulé représente théoriquement 1 unité.

Ce calcul n’est pas le relevé de compte d’un joueur. Les mises réelles, la cote obtenue, le moment de la prise de pari, les frais et le prix d’un abonnement peuvent modifier le résultat économique. Le ROI du tableau ne déduit pas le prix du service.

## Les données absentes comptent aussi

Si une cote manque, on ne connaît pas le gain net qu’aurait produit une victoire à mise fixe. Calculer un rendement sur les seules lignes complètes peut sélectionner involontairement une partie favorable de l’historique.

La page de résultats masque donc le ROI lorsqu’une cote nécessaire manque sur la période choisie. Les gains, pertes et paris annulés restent visibles. Les annulés ne sont pas comptés comme des victoires et sont exclus du ROI.

## Regarder la période et la provenance

Une série courte ne suffit pas à démontrer une performance durable. La période, le nombre de paris et les critères de regroupement doivent être lisibles. Des paris très liés entre eux n’apportent pas autant d’information que des observations indépendantes.

Les résultats présentés sur le site sont renseignés par StratEdge ; ils ne constituent pas une certification indépendante. Les publications du journal conservent leur propre trace éditoriale et ne sont pas additionnées une seconde fois au bilan des bets.
TEXT]
];
