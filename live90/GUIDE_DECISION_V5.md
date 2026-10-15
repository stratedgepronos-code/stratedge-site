# StratEdge Décision V5 — remplacement du Live V4

- Route inchangée : `/panel-x9k3m/slate/live90/`.
- `engine.py` appelle exclusivement `run_dashboard`. Aucun `notify`, enrichissement PulseScore ni livraison de résultats dans ce chemin de production.
- Les paramètres Telegram et PulseScore automatique passent à false ; l’API refuse leur réactivation ainsi que le test Telegram. Les clés restent conservées. Un test PulseScore manuel reste possible et consomme une requête.
- Les tables V4 et les anciens résultats sont conservés. Le suivi des anciens résultats Packball continue sans message.
- Migration SQLite additive : `v5_readings` (dernier état) et `v5_observations` (entrée dans un état à regarder, dédupliquée par relevé). Une observation n’est pas un pari et n’a pas de rendement financier.
- Le tableau recalcule sa lecture avec le relevé courant ; il ne réutilise pas l’ancien score d’intensité V4. Il indique une coupure après 100 s, une absence de moteur après 65 s, une pause ou une expulsion.
- Aucun événement n’est dû. Les moyennes FT sont des repères descriptifs, pas des prévisions à la minute. Aucun modèle de probabilité n’est prétendu calibré.
- Les buts/corners/jaunes déjà obtenus ne bloquent pas les suivants. Un changement du compteur ou de score réinitialise la confirmation. Les fenêtres ne traversent pas la pause et attendent des données après changement de score.
- Les seuils descriptifs sont visibles dans Comprendre. Ils doivent être évalués hors échantillon avant d’être interprétés comme sélection de paris.
- Le JSON analyste garde `stratedge.context.v4`. Ajout facultatif validé `teams.h/a.corner_reference`; anciens dossiers compatibles. Le prompt et la fiche de 19 cases sont téléchargeables depuis le module.
- CSS/JS restent intégrés au HTML par PHP. Aucune ressource nécessaire chargée depuis le chemin nginx `assets/` du panel.

Déploiement : sauvegarde SQLite et ancien moteur par `ops/deploy-live90-v4.sh`, installation des deux fichiers Python, redémarrage du même service. Le contrôle serveur exige désormais un cycle `decision5.0` et `telegram_active:false`. Installer ensuite le collecteur 5.0.0 dans le script Tampermonkey existant pour recevoir les nouveaux champs.
