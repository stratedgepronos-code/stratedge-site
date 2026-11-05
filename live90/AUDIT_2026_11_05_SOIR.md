# Audit du 5 novembre au soir — corrections techniques

Les seuils prédictifs, le prompt analyste R6 et le contrat `stratedge.context.v4` ne sont pas modifiés. Aucun appel IA live, nouvelle clé analyste ou envoi Telegram de test.

| Point | État du code | Vérification et limites |
|---|---|---|
| Preuves de règlement | Corrigé | Les bilans journaliers et la mémoire incluent `result_proofs` : verdicts courants, captures originales référencées par ces verdicts et leurs révisions, identifiants absents explicitement listés. HT/FT n’ont pas besoin d’être des évaluations LIVE pour être exportés. Une capture historiquement absente ne peut pas être recréée. |
| Rétractation Pafos | Mécanisme existant testé ; correction tardive améliorée | Rejeu synthétique 0→1→0, rouge, LIVE 90, une puis deux preuves FT, correction quatre jours après. Aucun échec à LIVE 90 ; aucun jaune déduit du rouge. Les résultats réglés datant de plus de trois jours sont désormais réexaminés si de nouvelles captures arrivent. Ce test ne prouve pas qu’une capture FT réelle de Pafos est disponible. |
| Pagination | Fonctionnement conservé et portée explicitée | `page_complete` : la page demandée est complète ; `complete` : cette réponse contient toute la journée depuis zéro ; `pagination_done` : aucune page suivante. Le manifeste `export_complete` est produit après vérification du total et des identifiants uniques. Tests plusieurs tailles et reprise au même plafond ; les nouvelles lectures après ce plafond sont exclues. Aucune continuité de surveillance déduite. |
| Jour du programme | Corrigé | `reference_day` est la date parisienne unique des rencontres, ou null pour un programme de plusieurs jours ; `fixture_dates` contient le détail. `export_day` indique séparément le jour d’export. Tests minuit et changements d’heure. Aucun horaire ni match déplacé. |
| Bilan et provenance | Corrigé | Tableau repliable par marché/période/voie/version dans l’historique, derniers verdicts des notifications réellement envoyées, matchs distincts, attentes/inconnus, contrôle première alerte par match/marché/période. Versions de collecteur figées au signal. Les futurs instantanés hachent les profils/contextes utilisés et ne prétendent plus connaître la révision du prompt ChatGPT. Les anciennes étiquettes restent historiques, sans attester R6. |
| Arrêts et reports | Tests de non-régression | Frontière 79:59/80:00 exacte, suivi indépendant après 80, états POSTPONED/OTHER/HT/FT non déclencheurs. Les références ≥0,8, deux captures, absence de tranches 15 min et valeurs null sont conservées. Un dossier avec watch=true n’autorise pas un signal sans état LIVE exploitable. |

## Collecte et interprétation

Les exports de couverture conservent la distinction reçu/traité/évalué, les motifs archivés et leurs limites. Une capture sans diagnostic ne prouve pas un trou pendant le jeu. Aucune classification rétrospective inventée pour les anciens relevés. Aucun taux d’occasions manquées ni rendement calculé.

Le bilan `learning.reviews` conserve les révisions d’analyse déjà enregistrées ; `result_proofs.current_results` et `breakdown` fournissent les verdicts courants au moment de l’export, y compris les alertes en attente qui n’ont pas encore de revue. Les deux sont lus dans la même transaction SQLite. Les statistiques d’historique séparent le radar des anciens signaux V4.

## Déploiement et espace disque

Nettoyage autorisé par le propriétaire le 5 novembre : exactement huit fichiers `live90.sqlite.gz` des 22–23 octobre, 4 544 638 408 octets. Inventaire exact dans `ops/live90-cleanup-approved-20261105.py`. Aucun dossier, historique actif ou autre archive supprimé. Vérification intégrale des deux sauvegardes SQL conservées les plus récentes avant suppression, contrôle tailles/liens, reçu d’exécution idempotent. Les seize autres sauvegardes sont conservées. La sauvegarde normale préalable au déploiement reste obligatoire.

Après installation : mettre à jour le script Tampermonkey existant en 5.2.0 et conserver un seul collecteur actif. Aucun nouvel export analyste requis pour ces correctifs. Le suivi réel des prochains relevés reste nécessaire pour confirmer la collecte et les preuves terminales en production.
