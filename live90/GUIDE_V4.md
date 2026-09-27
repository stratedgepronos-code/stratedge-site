# StratEdge Live V4 — livraison et choix de fonctionnement

## Périmètre

Nouveau module remplaçant la page et le moteur de production, même URL `/panel-x9k3m/slate/live90/`. Deux imports indépendants : JSON analyste V4 et deux CSV Packball A/B. Chaque rencontre reste visible. Les IDs sont associés automatiquement par noms normalisés et coup d’envoi ±60 s, sans appariement approximatif ambigu. Un nom différent doit être corrigé dans la source, jamais deviné.

Trois marchés, chacun h/a : but supplémentaire avant HT, but supplémentaire avant FT, premier carton de l’équipe (+0,5 FT). Pas de total match, prochain but, combiné ni pari placé automatiquement. Une cote bet365 observée via Packball n’est pas une cote Stake.

## Méthode initiale, à mesurer

Ce sont des règles expérimentales, pas un modèle prédictif calibré. Aucun p_est, edge ou taux de réussite inventé. Intensité /100 = score explicable de conditions, jamais pourcentage de chance.

Buts : fenêtre 12–42′ HT, 15–82′ FT ; au moins 4 tirs et 2 cadrés sur 10 minutes et rythme cumulé de tirs >=1,15 fois le repère linéaire avant-match. Intensité : volume récent (25), cadrés récents (30), rythme relatif (25), cadré dans les 5 dernières minutes (10), équipe ne menant pas (10). Seuil initial 70 ; contexte sourcé d’absences offensives/fatigue/rotation/météo relève ce seuil au maximum à 82, sans retirer le match. Les données tardives ne sont pas antidatées comme avant-match.

Cartons : équipe sans jaune ni rouge, 15–78′, au moins 5 fautes cumulées et 3 sur les dix dernières minutes ; soutien par possession <=48 % ou au moins 3 tirs adverses sur 10 minutes. Intensité : fautes récentes (40), cumul (20), possession (15), tirs adverses (15), équipe ne menant pas (10). Seuil initial 70. La possession seule ne déclenche jamais.

Pour tous : cote exacte, >1, cote minimale réglable 1,65 par défaut et plafond 5,00 ; deux relevés concordants, séparés de 20–100 s ; score et ligne inchangés ; données <=100 s, cote <=90 s. Pas d’expulsion, prolongation ou temps additionnel non modélisé. Après un but observé, une nouvelle fenêtre complète est requise. Une expulsion suspend le traitement du match car les règles ont été conçues à 11 contre 11, sans retirer la ligne du tableau.

Déduplication : un signal par match/équipe/marché/ligne. Un but déjà inscrit peut permettre une nouvelle ligne après une nouvelle dynamique ; HT et FT sont corrélés et ne constituent pas deux preuves indépendantes. Les seuils n’ont pas été calibrés sur un historique hors échantillon. L’historique sert à les évaluer avant toute affirmation de rentabilité.

## Historique et Telegram

Tables `v4_*` ajoutées à la base existante. Aucun effacement de cycles, samples, signaux ou résultats antérieurs. Chaque signal fige statistiques, contexte, version, ligne, cote et horodatage. La livraison est distincte du verdict sportif. `failed` = refus certain, `uncertain` = réponse réseau ambiguë, pas de renvoi aveugle. Les HTTP 400/401 sont failed ; les signaux dont les conditions ou la cote ont changé avant envoi expirent. Une livraison interrompue par un crash devient uncertain. Les requêtes 429 ne sont pas renvoyées automatiquement en retard.

Le bouton **Envoyer un test Telegram** utilise la configuration existante dans `/etc/stratedge/live.env` sans l’afficher. Un contrôle « clés présentes » ne prouve pas la réception réelle.

Le résultat sportif est confirmé manuellement (gagnant/perdant/annulé/en attente) avec une source. Les scores HT/FT observés peuvent suggérer un verdict pour les buts ; les cartons demandent le règlement du bookmaker. Toute correction est journalisée. Les gains affichés sont une simulation à 1 unité par signal, pas un relevé des mises réellement placées.

## Sources et limites

- Telegram : https://core.telegram.org/bots/api#sendmessage
- Règlement cartons bet365 : https://help.bet365.com/s/en/sportsrules/soccer/card-markets
- Packball : https://packball.com/

Le mécanisme d’envoi utilise l’API officielle Telegram. Le moteur ne consulte aucune IA. Les absences nouvelles et compositions modifiées ne sont pas recherchées pendant le match. Les moyennes Packball ne sont pas considérées H/A sans preuve du réglage de l’échantillon. La validation JSON contrôle structure et références, pas la véracité des recherches GPT.

Les cotes par équipe HT et cartons ne sont pas établies dans la liste de filtres fournie : ces deux marchés restent en attente d’une cote exacte tant qu’un relevé réel ne confirme pas leur disponibilité. Voir FILTRES_LIVE_V4.md. Pas d’invention de filtres ou de cotes.

La collecte dépend toujours d’un navigateur Packball authentifié et actif ; les règles n’évitent pas les limites du DOM, des onglets suspendus et de la veille. Le badge V4 fonctionne sur navigation interne et se réattache après une reconstruction du DOM. Pas de contournement d’un 401 Packball.

## Déploiement

Les CSS/JS sont intégrés côté PHP dans le HTML (restriction nginx du panel conservée). Authentification super-admin et CSRF maintenues. Les deux arborescences public_html et live90/public sont synchronisées.

Le script ops/deploy-live90-v4.sh sauvegarde le code et la base par l’API de sauvegarde SQLite, teste puis remplace uniquement les fichiers serveur du module et redémarre seuil90-live. L’ancien moteur reste présent pour l’audit, mais le point d’entrée engine.py exécute live_v4.run. Les clés et autres services ne sont pas modifiés. En cas d’échec de redémarrage, le point d’entrée précédent est restauré ; la migration est additive et compatible avec l’ancien schéma.
