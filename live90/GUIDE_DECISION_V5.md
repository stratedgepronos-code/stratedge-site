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


## Cuts et cotes Stake dans le radar

Le bouton « Cuts Stake · 1 requête » consulte la page live du fournisseur à la demande, avec la clé existante. Aucun appel automatique. Une minute minimum entre deux appels ; compteur local plafonné à 500 sur 31 jours. La réponse est limitée aux 30 matchs de la page reçue : un match non associé n’est pas déclaré indisponible chez Stake.

Les marchés reconnus sont les totaux par équipe de buts, corners et Bookings. La carte utilise la plus petite ligne FT en demi-unité reçue qui n’est pas déjà dépassée ; la fiche affiche aussi les autres lignes et les buts de première mi-temps lorsqu’ils sont disponibles. Les totaux du match, prochaines équipes à marquer, corners suivants et tranches de minutes ne les remplacent jamais. Les prix contradictoires ou non confirmés actifs sont écartés.

Exemple corners : 3 corners observés et over 5,5 → 3 corners supplémentaires nécessaires. Exemple buts : 1 but et over 1,5 → 1 but supplémentaire nécessaire. L’écart n’est pas une prédiction, et un signal pour un événement n’établit pas l’intérêt d’un cut qui en demande plusieurs.

Pour les Bookings, les jaunes Packball sont un compteur différent : l’écart reste conditionnel à un même décompte, jamais une équivalence certifiée. Aucune conversion simple après expulsion, avec compteur manquant ou pour une ligne asiatique/entière. Les cotes expirent pour l’affichage après 60 secondes depuis la réception locale ; leur horodatage d’origine n’est pas garanti. Vérifier ligne, période, unité et prix sur Stake.
