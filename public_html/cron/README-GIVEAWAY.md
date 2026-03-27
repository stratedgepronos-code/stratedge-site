# GiveAway StratEdge

## Fichiers
- `/giveaway.php` — page membre (roue anonymisée, points)
- `/admin/giveaway.php` — tirage, cadeau, classement
- `includes/giveaway-functions.php` — tables + logique
- `cron/giveaway-reminder.php` — rappel email au super admin

## Cron (dernier jour du mois, 21h Europe/Paris)

Le script vérifie tout seul si c’est le dernier jour du mois.

```text
0 21 28-31 * * /usr/bin/php /chemin/vers/public_html/cron/giveaway-reminder.php
```

## Panel `panel-x9k3m`

Le dépôt contient **`panel-x9k3m/giveaway.php`** : il charge `admin/giveaway.php`. Après déploiement Git, l’URL  
`https://stratedgepronos.fr/panel-x9k3m/giveaway.php` fonctionne sans copie manuelle.

## Points
Daily 1 · Week-End 3 · Weekly 6 · VIP Max 10 — Tennis / Fun : 0 pt.

Les points sont ajoutés via `activerAbonnement()` (includes/auth.php) et validation paiement crypto (crypto-admin).
