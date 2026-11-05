# Correctifs du radar — audit du 5 novembre 2026

## Comportement livré

- Collecteur Tampermonkey **5.2.0** : identifiant d’installation persistant, identifiant de session distinct, conservation du token existant.
- Ingestion : une source saine prioritaire. Une version plus récente et saine peut prendre la main ; une source concurrente ne remplace pas le tableau pendant les 100 secondes de fraîcheur du producteur choisi. Reprise possible après expiration. Réponse HTTP 409 explicite pour la source secondaire, aucune insertion de ses captures. Pas de fusion de champs entre producteurs.
- Diagnostic : versions, source retenue, présence des tacles et avertissement de collectes concurrentes dans le panel. Les versions sont déclarées par le collecteur ; elles ne constituent pas une attestation cryptographique.
- Continuité : un bond de minute supérieur au temps écoulé plus la tolérance technique est mis en quarantaine avant de modifier le repère durable. Les régressions, anciens états et réouvertures après FT restent bloqués.
- Les anciens repères de continuité sont reconstruits depuis les captures brutes, par lots bornés. Les incidents antérieurs sont conservés dans `v5_stream_incident_revisions`. Aucun effacement des captures, alertes ou résultats.
- Messages distincts pour fenêtre en constitution, remise à zéro après but et données réellement absentes. Les motifs détaillés sont archivés.
- Une voie de recherche séparée recalcule les sous-statistiques de tirs avec des ancres communes. Elle reste `shadow_only` : elle ne change ni les métriques de déclenchement ni les notifications. Pas d’ancres cumulées traversant un changement de collecteur/session.
- Le bouton bilan utilise désormais la journée Europe/Paris sélectionnée. Alertes créées ce jour, dernière révision disponible, anciens signaux séparés et incidents de la journée. Le bouton mémoire conserve volontairement l’historique global.

## Invariants

Les seuils sportifs ne changent pas : références localisées de mi-temps ≥0,8 marqué et ≥0,8 encaissé, conditions de momentum actuelles, absence de tranche de 15 minutes, arrêt des nouvelles alertes dès 80:00, suivi des résultats conservé, marchés cartons par équipe selon la liste configurée. Aucun appel à une IA live, aucune mise automatique, aucun envoi Telegram de test.

Les corrections techniques peuvent rendre à nouveau analysable une rencontre auparavant bloquée à tort. Les compteurs inconnus restent inconnus. Un historique de conversion tirs/buts ne crée aucune dette de but.

## Mise en service

1. Déployer les fichiers serveur avec la procédure existante et sa sauvegarde. Les fichiers sous `live90/public/` et `public_html/` sont synchronisés ; CSS et JavaScript restent intégrés dans la page.
2. Dans les réglages du module, télécharger le collecteur **5.2.0** et mettre à jour le script existant dans Tampermonkey. Le nom/namespace et la clé du token restent identiques.
3. Garder un seul script et un seul onglet de collecte actif. Le diagnostic doit identifier la source retenue et la version reçue.
4. Aucun nouvel import du JSON analyste n’est nécessaire pour ces correctifs.

Les anciennes versions continuent de fonctionner si elles sont seules. Le serveur ne peut pas installer la mise à jour dans le navigateur de l’utilisateur. Le premier traitement de matchs possédant beaucoup d’anciennes captures peut afficher un contrôle de continuité en cours pendant plusieurs cycles.

## Validation

Les tests couvrent le trajet Monaco 44→90→46, progression normale avec 45+ et HT, correction de score, interruption de collecte, reconstruction après redémarrage, anciens incidents conservés, relevés non interprétables, sources concurrentes, mise à jour prioritaire, reprise après expiration, refus HTTP sans écrasement, fenêtres inconnues/zéro, calculs alignés sans effet prédictif et bornes du jour Europe/Paris.

Les scénarios historiques de recette qui faisaient avancer l’horloge sportive de plusieurs dizaines de minutes en quelques secondes ont été corrigés pour simuler une durée réellement écoulée ; leurs assertions sur les marchés et résultats sont maintenues.

## Travaux prédictifs volontairement séparés

La calibration selon le temps restant, l’estimation du délai jusqu’au carton, l’ajustement arbitre/ligue et l’ajout de données joueur/type de faute nécessitent une expérimentation prospective. Cette livraison ne prétend pas valider leur efficacité ou une rentabilité. Les cotes et lignes doivent toujours être archivées pour une future mesure économique.
