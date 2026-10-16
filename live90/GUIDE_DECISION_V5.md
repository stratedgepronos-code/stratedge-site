# StratEdge Décision V5 — remplacement du Live V4

- Route inchangée : `/panel-x9k3m/slate/live90/`.
- `engine.py` appelle exclusivement `run_dashboard`. Il relaie uniquement les cartes radar confirmées via Telegram. Aucun enrichissement PulseScore automatique ni livraison des anciens résultats.
- Les anciens paramètres Telegram et PulseScore automatique restent à false. Le radar utilise son propre paramètre `radar_telegram_enabled`, activé par défaut et modifiable dans Réglages ; le test Telegram est disponible. Les clés restent conservées. Un test PulseScore manuel reste possible et consomme une requête.
- Les tables V4 et les anciens résultats sont conservés. Le suivi des anciens résultats Packball continue sans message.
- Migration SQLite additive : `v5_readings` (dernier état) et `v5_observations` (entrée dans un état à regarder, dédupliquée par relevé). Une observation n’est pas un pari et n’a pas de rendement financier.
- Le tableau recalcule sa lecture avec le relevé courant ; il ne réutilise pas l’ancien score d’intensité V4. Il indique une coupure après 100 s, une absence de moteur après 65 s, une pause ou une expulsion.
- Aucun événement n’est dû. Les moyennes FT sont des repères descriptifs, pas des prévisions à la minute. Aucun modèle de probabilité n’est prétendu calibré.
- Les buts/corners/jaunes déjà obtenus ne bloquent pas les suivants. Un changement du compteur ou de score réinitialise la confirmation. Les fenêtres ne traversent pas la pause et attendent des données après changement de score.
- Les seuils descriptifs sont visibles dans Comprendre. Ils doivent être évalués hors échantillon avant d’être interprétés comme sélection de paris.
- Le JSON analyste garde `stratedge.context.v4`. Ajout facultatif validé `teams.h/a.corner_reference`; anciens dossiers compatibles. Le prompt et la fiche de 19 cases sont téléchargeables depuis le module.
- CSS/JS restent intégrés au HTML par PHP. Aucune ressource nécessaire chargée depuis le chemin nginx `assets/` du panel.

Déploiement : sauvegarde SQLite et ancien moteur par `ops/deploy-live90-v4.sh`, installation des deux fichiers Python, redémarrage du même service. Le contrôle serveur exige désormais un cycle `decision5.0` et `legacy_telegram_active:false` et `notification_mode:confirmed_cards`. Installer ensuite le collecteur 5.0.0 dans le script Tampermonkey existant pour recevoir les nouveaux champs.


## Cuts et cotes Stake dans le radar

Le bouton « Cuts Stake · 1 requête » consulte la page live du fournisseur à la demande, avec la clé existante. Aucun appel automatique. Une minute minimum entre deux appels ; compteur local plafonné à 500 sur 31 jours. La réponse est limitée aux 30 matchs de la page reçue : un match non associé n’est pas déclaré indisponible chez Stake.

Les marchés reconnus sont les totaux par équipe de buts, corners et Bookings. La carte utilise la plus petite ligne FT en demi-unité reçue qui n’est pas déjà dépassée ; la fiche affiche aussi les autres lignes et les buts de première mi-temps lorsqu’ils sont disponibles. Les totaux du match, prochaines équipes à marquer, corners suivants et tranches de minutes ne les remplacent jamais. Les prix contradictoires ou non confirmés actifs sont écartés.

Exemple corners : 3 corners observés et over 5,5 → 3 corners supplémentaires nécessaires. Exemple buts : 1 but et over 1,5 → 1 but supplémentaire nécessaire. L’écart n’est pas une prédiction, et un signal pour un événement n’établit pas l’intérêt d’un cut qui en demande plusieurs.

Pour les Bookings, les jaunes Packball sont un compteur différent : l’écart reste conditionnel à un même décompte, jamais une équivalence certifiée. Aucune conversion simple après expulsion, avec compteur manquant ou pour une ligne asiatique/entière. Les cotes expirent pour l’affichage après 60 secondes depuis la réception locale ; leur horodatage d’origine n’est pas garanti. Vérifier ligne, période, unité et prix sur Stake.


## Notifications du radar — 16 octobre 2026

Les cartes « Dynamique confirmée » (buts, corners, cartons par équipe) déclenchent une notification serveur, même sans page StratEdge ouverte. Packball et le collecteur doivent continuer à fournir des relevés. Les simples cartes factuelles « À surveiller » ne déclenchent pas d’envoi. Aucun changement des seuils d’analyse ni du prompt analyste.

La table additive `v5_radar_notifications` conserve chaque notification, son message, son état de livraison et l’identifiant Telegram. La déduplication persiste après redémarrage : une notification par match, équipe, marché et compteur. Un compteur nouveau permet un nouveau signal, avec dix minutes minimum depuis le précédent du même type. Le réglage d’arrêt est persistant et annule la file en attente. Les observations exposent leur état Telegram lorsqu’un envoi correspond au relevé ; Réglages présente aussi le bilan des livraisons.

Avant envoi, le moteur relit le dernier relevé et exige une dynamique encore confirmée, un score et un compteur inchangés, un match encore dans la sélection et en jeu. Les relevés de plus de 100 secondes et la file de plus de 90 secondes expirent. Trois messages maximum par cycle, espacés de 1,1 seconde pour limiter les rafales. Une interruption pendant l’envoi est classée `uncertain` et n’est jamais renvoyée automatiquement ; un refus HTTP est `failed`.

Les cuts proviennent exclusivement du cache PulseScore existant, encore valide (60 secondes). Aucune requête de cotes supplémentaire. Équipe, marché FT, ligne, cote et événements supplémentaires requis sont explicités. Bookings et jaunes restent distingués. Une cote sous 1,50 est signalée comme telle. Sans cote actuelle, le message est envoyé avec « Cote et cut indisponibles · à vérifier sur Stake ». Il s’agit d’une observation statistique, pas d’un pari validé, et aucun résultat financier n’est créé.
