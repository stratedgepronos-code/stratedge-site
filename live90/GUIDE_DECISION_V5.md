## Extension cartons UEFA — 21 octobre 2026

La sélection cartons inclut maintenant neuf compétitions : les sept championnats déjà choisis, plus UEFA Champions League et UEFA Europa League. Une migration unique ajoute ces deux compétitions aux réglages existants sans réactiver les autres ligues retirées ni changer l'état de Telegram. Les modifications manuelles ultérieures restent conservées. Reconnaissance exacte des libellés anglais/français avec la région Europe/EUR/UEFA ; pas d'inférence depuis le pays des clubs. Les autres coupes, la Conference League, les femmes, jeunes et Champions League d'autres continents restent exclues par défaut. L'autorisation s'applique au tableau, au radar et à Telegram ; elle ne garantit ni un marché bookmaker disponible ni une alerte sans données avant-match/live cohérentes. Le prompt téléchargeable demande aussi la recherche disciplinaire pour ces deux compétitions. Aucun changement de seuil ni de suivi des résultats.

## Période des références alignée sur le pari — 18 octobre 2026, soirée

Le radar buts suit une cible FT : les références avant-match portent désormais sur MATCH ENTIER, avec les localisations domicile/extérieur du jour. La minute live ne sélectionne plus une mi-temps historique comme condition obligatoire. Aucun nouveau marché HT n’est activé. Les résultats déjà figés et les règles cartons sont inchangés.

Le JSON garde `stratedge.context.v4` et `goal_halves`, avec un champ facultatif `full_time` (mêmes clés scored_per_match/conceded_per_match que les mi-temps). Les moyennes FT explicites sont prioritaires ; les anciens objets sans cette clé peuvent fournir le FT par somme des deux mi-temps complètes sur le même échantillon. Une seule mi-temps, une localisation globale ou une valeur manquante ne sont jamais extrapolées. Les mi-temps peuvent être omises si le FT est documenté. Le prompt téléchargeable est mis à jour. Aucun import ne contourne le gel avant-match.

## Concordance obligatoire — 18 octobre 2026, après-midi

Les seuils live ne suffisent plus. Les deux relevés qui confirment une alerte doivent chacun présenter `coherence.status=confirmed`. Le radar, les cartes actives et Telegram appliquent le même contrôle. Un dossier absent/tardif ou une donnée requise manquante laisse le match visible avec le motif précis, sans alerte. Les résultats des alertes déjà envoyées restent suivis.

Buts : profil et contexte avant-match exploitables, n≥10 par équipe, buts marqués et encaissés adverses ainsi que tirs/cadrés positifs ; tirs ET cadrés live sur 10 minutes supérieurs à leurs moyennes FT divisées par neuf. Références FT par localisation avec n≥10, production marquée et encaissée positive. Aucun seuil de mi-temps pour un pari FT. Les critères d’activité minimale et de continuité 5/10 minutes restent obligatoires. Politique `prematch_live_period_v3` partagée par serveur et interface ; anciennes lectures actives recalculées avant tout envoi.

Cartons : ligue autorisée, profil et contexte exploitables, arbitre confirmé avec n≥10 et moyennes jaunes/fautes, jaunes par équipe dans les localisations du jour avec n≥10. La moyenne de jaunes de l’arbitre (deux équipes) doit au moins égaler la somme des deux moyennes d’équipe ; la moyenne de l’équipe ciblée doit être positive. Ses fautes live sur 10 minutes doivent dépasser `fouls_for_per_match / 9`, et la somme des fautes live des deux équipes doit dépasser les fautes moyennes de l’arbitre / 9. Les critères d’activité live et pression adverse restent requis. Un jaune existant ne désactive pas le suivant. H2H contextuel seulement.

Ces règles sont une politique expérimentale explicite, pas un modèle calibré ni la preuve d’un avantage. FT/9 est une cadence descriptive uniforme ; elle ne prédit pas les minutes restantes. Aucun « retard » de buts/cartons ne suffit. Le prompt analyste demande désormais les fautes commises, documentées sur le même échantillon que les jaunes (`fouls_for_per_match`, facultatif/null dans les anciens imports). Ne pas antidater un enrichissement tardif pour débloquer le système.

## Résultats des alertes Telegram — 18 octobre 2026

Chaque nouvelle alerte effectivement envoyée conserve une cible FT figée, son compteur initial, son cut exact si disponible et sa cote indicative. Sans cut, le suivi concerne un événement supplémentaire pour cette équipe avant la fin du match. Le résultat et les corrections sont enregistrés dans `v5_radar_results` / `v5_radar_result_events`, visibles dans les observations et envoyés en réponse au message Telegram d’origine.

Deux relevés distincts concordants (15 à 120 s) après l’alerte confirment un seuil atteint. Un seuil non atteint exige deux relevés FT explicites ; la 90e minute, une pause ou la disparition du programme ne suffisent jamais. Une correction Packball retire la validation et est notifiée. Après six heures sans conclusion exploitable : résultat à vérifier ; une donnée ultérieure peut encore le résoudre. Aucun ancien message sans cible figée n’est réinterprété, aucun appel de cotes/IA et aucun gain financier calculé. Les résultats des alertes déjà envoyées restent suivis même si la ligue est retirée ou le match masqué.

Un cut Bookings est suivi seulement à titre indicatif en jaunes Packball : le message rappelle explicitement que le règlement Stake n’est pas certifié. Un cut +2,5 ne devient pas gagné après un seul jaune. Les refus HTTP sont `failed`, les livraisons réseau ambiguës `uncertain`, sans renvoi aveugle. Les doublons de relevés ne créent aucun nouvel envoi. Le réglage Telegram contrôle alertes et résultats.

## Championnats cartons choisis le 18 octobre 2026

Migration unique : MLS, Angleterre/Premier League, Espagne/La Liga, Italie/Serie A, Allemagne/Bundesliga, France/Ligue 1, Arabie saoudite/Saudi Pro League. La liste est visible et modifiable dans Réglages. Pays du CSV Packball + libellé exact de championnat, avec synonymes explicites ; aucun rapprochement par sous-chaîne ni par nom d’équipe. Donnée pays absente ou championnat hors sélection : compteurs conservés, recommandations cartons désactivées sur le tableau, le radar et Telegram. Les U21, féminines, réserves, coupes, deuxièmes divisions et autres pays ne sont pas assimilés aux sept championnats. Le mode strict empêche même une cote reçue de contourner cette liste. Cette sélection prime sur l’ancien repli « marché vérifié ». Les buts restent indépendants. Les anciennes observations restent dans l’historique ; aucune nouvelle observation cartons hors sélection n’est créée.

# Mise à jour du 18 octobre 2026 — buts et cartons

- Corners retirés des lectures courantes, filtres et alertes Telegram. Les archives, anciens imports et décodeurs de marchés restent compatibles ; aucune suppression d’historique.
- Le prompt téléchargeable `PROMPT_ANALYSTE_V4.md` demande la recherche web avant-match : désignation/arbitre (StatsHub ou source officielle), cartons de chaque équipe global/home/away/H2H, buts marqués et concédés par mi-temps (FootyStats ou autre source vérifiée). Aucun nouvel abonnement ni scraping en boucle, aucun appel IA live.
- Contrat `stratedge.context.v4` conservé. Champs facultatifs validés `teams.h/a.card_references` et `goal_halves` (ancien `goal_timing` accepté) : source, période, nombre de matchs et date de référence avant le coup d’envoi obligatoires. Les anciens dossiers restent acceptés. Les nouvelles données exigent un nouveau JSON importé avant le match ; une ancienne analyse n’est pas enrichie automatiquement.
- Affichage compact et message Telegram incluent l’arbitre, les moyennes des deux équipes à leur localisation du jour, le global et H2H. Les autres splits sont conservés dans le JSON. Un décompte « cards » ou « booking_points » ne remplace jamais les jaunes.
- Buts : références par mi-temps et localisation décrites ci-dessus ; les anciennes tranches ne pilotent plus les alertes.
- Aucun xG de tirs fiable actuellement collecté : pas de seuil xG inventé, pas d’assimilation ExG Packball / xG cumulé. Les tirs/cadrés récents restent la preuve live disponible.
- Réglages → championnats cartons : liste de noms Packball exacts, un par ligne. Seules ces ligues OU un marché FT de cartons de la bonne équipe effectivement reçu, actif et frais chez Stake autorisent Telegram cartons. Liste vide initialement ; la surveillance à l’écran et les alertes buts continuent. Aucune liste de couverture Stake inventée ; une ligue autorisée ne garantit pas chaque match.
- La présence d’un total cartons MATCH, d’un marché adverse ou d’une ancienne cote ne permet pas l’envoi pour l’équipe ciblée. Aucun appel PulseScore automatique ajouté (quota conservé).
- Les champs manquants ne deviennent pas zéro. Ni les historiques ni une série de fautes ne créent un événement « dû ». H2H et arbitre décrivent un contexte, pas une probabilité calibrée.

Les notes ci-dessous décrivent aussi des versions antérieures ; cette mise à jour prime pour les corners et les champs analyste.

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

Le déploiement vérifie la place avant sa sauvegarde. Si nécessaire, les anciennes copies SQLite sont compressées en `.sqlite.gz`, avec comparaison SHA256 après décompression avant de retirer la copie non compressée. Aucun historique n’est supprimé. Le contrôle ne compresse que si nécessaire : 2 Gio de marge pour le fonctionnement courant, auxquels s’ajoute la taille de la nouvelle sauvegarde si le moteur installé doit changer. Pour restaurer une copie, décompresser dans un nouveau fichier privé avant les contrôles SQLite et la restauration.

