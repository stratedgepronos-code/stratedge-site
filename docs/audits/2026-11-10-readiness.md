# Préparation des prochaines journées — 10 novembre 2026

## Installé, sans changement de notifications

- Diagnostic health research_stats : six statistiques complémentaires absentes/vides signalées, zéro mesuré accepté. Ce diagnostic ne change pas les règles d’éligibilité. La cause (case absente ou donnée fournisseur vide) n’est pas inventée.
- Chaque fenêtre évaluée archive prospective_research, politique research_20261110_v1. Aucun accès à Telegram, aucun changement de d.status, aucun appel API supplémentaire.
- Les données, compteurs, ancres et entrées restent liés aux identifiants et hashes de l’archive de fenêtres existante. Réimport context.v4 et changement Tampermonkey non nécessaires.

## Candidats figés, non validés

Le domaine est celui des matchs réellement évalués, avec les données disponibles. Les frontières des nouvelles alertes, la fraîcheur, les expulsions et la continuité sportive restent respectées. Les exclusions de ligue cartons sont conservées. Une feature indisponible produit null, jamais zéro.

| Identifiant | Domaine | Définition |
|---|---|---|
| current_plus_da055 | Buts, 57–75 min incluses | Règle active confirmée ET attaques dangereuses cumulées/minute affichée ≥0,55 |
| current_plus_ftsum33 | Buts, 57–75 min incluses | Règle active confirmée ET somme GF équipe + GA adversaire du profil FT avant-match ≥3,3 |
| ht_ftsum33_exploratory | Buts, 15–25 min incluses | Même somme FT ≥3,3, observation uniquement |
| cards_fouls014_yellow2 | Cartons, 57–75 min incluses | Fautes cumulées/minute ≥0,14 ET jaunes historiques de l’équipe dans sa localisation ≥2 |
| cards_fouls014_low_yellow_not_leading | Cartons, 57–75 min incluses | Fautes cumulées/minute ≥0,14 ET jaunes actuels ≤1 ET équipe ne mène pas |

Les candidats buts supplémentaires filtrent la vraie règle confirmée, pas son proxy simplifié. La somme FT conserve le périmètre Packball sans inventer sa localisation. Les références cartons sont localisées et utilisables avant-match. Les candidats cartons ne dépendent pas de la possession mais ne modifient pas la règle active. Les cartons HT ne sont pas activés.

Ces valeurs évaluées à chaque fenêtre ne sont ni des alertes ni des résultats. Dédupliquer la première situation vraie par match/équipe/marché/période/politique. Relier les résultats à des preuves terminales et conserver les inconnus. Aucune promotion automatique après 150 observations. Comparer aux mêmes instants et sur les mêmes matchs, avec couverture, succès supprimés, échecs évités et incertitude regroupée par match. Figer la date de fin avant examen des résultats.

## Audit historique corrigé, résultats à recalculer

ops/audit/mesure_live90.py reste en lecture seule : plus de substitution LIVE 46/47 pour HT ni LIVE 90+3 pour FT. Deux dernières preuves terminales cohérentes requises ; corrections et captures périmées neutralisées. Toutes les révisions FT sont lues. Les profils/contextes tardifs ou sans antériorité vérifiable sont exclus des comparaisons avant-match. Les règles simplifiées sont nommées proxys partiels : les taux bruts ne prouvent pas une amélioration si instants et populations diffèrent.

Ce script exploratoire ne remplace pas un rejeu complet, par version, des entrées figées. Aucun nouveau pourcentage validé annoncé. Pas d’API de fautes par joueur ajoutée, pas de cote Stake déduite de bet365. Les horodatages fournisseur absents restent inconnus. Pas de rentabilité sans prix exécutable et règlement.

## Action sur le poste Packball

Garder un seul collecteur actif et vérifier les six cases de FILTRES_LIVE_V4.md. Le serveur ne peut pas cocher les cases du navigateur local. Les colonnes sont déjà reconnues par le collecteur : pas de réinstallation nécessaire.

## Contrôle de déploiement et export

Le contrôle réel a atteint la limite de 35 secondes sur export_window_audit après installation. La requête paginée utilise maintenant explicitement l’index de date existant, pour éviter un parcours de tout l’historique par identifiant. Le délai limite reste inchangé. La correction ne modifie que le plan de lecture ; ordre, plafond, curseur et contenu sont conservés. Le déploiement de cette seule substitution SQL est protégé par une comparaison exacte du fichier complet et une sauvegarde du code avec retour arrière ; tout autre changement moteur conserve la sauvegarde complète de la base. La sauvegarde complète réalisée avant cette mise à jour reste disponible.
