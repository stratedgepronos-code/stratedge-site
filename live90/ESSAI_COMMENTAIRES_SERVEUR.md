# Tester les commentaires sur les matchs du radar

Sonde d'observation côté serveur pour **APIfootball.com (sans tiret)**. Pas de changement du moteur, du prompt ou de Telegram. N'installe aucun service. La clé du lecteur SoccersAPI et celle de PulseScore ne conviennent pas.

Le script lit la base Live90 en lecture seule. Il retient le programme actif, les rencontres en direct en dessous de la coupure de 80 minutes, avec une lecture cartons autorisée et récente, issue du dernier relevé. Il ne crée ni pronostic ni texte de match. S'il y a au moins un match à vérifier, il fait une seule requête groupée, quel que soit le nombre de matchs du programme. Sinon : zéro requête. Deux instances feraient deux appels : ne lancer qu'un essai à la fois.

Réglages privés du service : `SE90_DB` (base locale existante), `SE90_APIFOOTBALL_COM_KEY` (clé dédiée) et `SE90_COMMENTARY_TZ` (fuseau IANA réellement utilisé par la source pour les coups d'envoi, à confirmer avec une rencontre connue ; ne pas présumer le fuseau Packball). Utiliser le mécanisme habituel de variables d'environnement sur le serveur, sans clé dans la ligne de commande ni dans le dépôt. Exécuter depuis le dépôt avec un compte qui lit déjà la base :

```sh
python3 live90/server/commentary_probe.py --cycles 90
```

Au plus 90 appels, espacés d'au moins une minute ; arrêt à la première erreur, y compris HTTP 401, 403 et 429. Aucun texte brut ni clé dans le rapport : une ligne JSON par passage, ventilée par ligue, uniquement sur la sortie standard. Pas de stockage automatique. Les compteurs sont des instantanés : ne pas les additionner au fil du match, une même action peut revenir. Un appariement exige les deux équipes dans le bon ordre et la même minute de coup d'envoi en UTC. Alias non confirmé, collisions et heures locales ambiguës restent non appariés.

Indicateurs : rencontres du radar, appariées/non appariées, sans texte, avec texte horodaté, avec au moins une formulation explicite reconnue de faute commise avec nom et équipe, avec au moins un rappel verbal explicite. Ce petit dictionnaire FR/EN donne un minimum détecté, pas une couverture exhaustive ; il ne valide pas les noms contre les compositions. Aucune déduction du seul libellé « coup franc ». Aucun envoi Telegram et aucune vérification d'antériorité destinée à une décision de pari dans cette sonde.

Avant tout enrichissement des notifications, vérifier sur un échantillon réel par ligue : attribution joueur/équipe contre compositions, minute de réception personnelle par rapport au signal (le flux peut rejouer de vieilles phrases), différence entre faute commise/subie et jaune déjà donné, déduplication et corrections. Une ligue annoncée au catalogue ne garantit pas ces données. L'absence de texte ne bloque jamais le radar normal.

[Documentation du flux](https://apifootball.com/documentation/#LiveOdds) · [Tarifs affichés](https://apifootball.com/). Le plan gratuit annonce Championship et Ligue 2, qui peuvent être absents de la sélection cartons du radar. Ne pas élargir les ligues autorisées pour tester ; choisir un accès d'essai couvrant une soirée de la sélection.
