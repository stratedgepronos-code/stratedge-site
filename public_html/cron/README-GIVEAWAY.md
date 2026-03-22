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

Si ton admin est servi sous `/panel-x9k3m/`, copie aussi **`admin/giveaway.php`** à cet emplacement (ou même alias) pour que le lien du mail de rappel fonctionne — ou adapte l’URL dans `giveaway-reminder.php`.

## Points
Daily 1 · Week-End 3 · Weekly 6 · VIP Max 10 — Tennis / Fun : 0 pt.

Les points sont ajoutés via `activerAbonnement()` (includes/auth.php) et validation paiement crypto (crypto-admin).
