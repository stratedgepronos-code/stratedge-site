# File d’attente notifications (email + push)

## Problème résolu

L’envoi à **tous les abonnés** dans la même requête PHP provoquait souvent un **timeout** (30–60 s) : les tests (1 destinataire) passaient, la prod (dizaines/centaines) coupait avant la fin → **aucun mail ni push**.

## Fonctionnement

1. Au post d’un bet, les destinataires sont **enfilés** dans la table `notif_queue`.
2. Le script traite **par paquets** (80–120 personnes) avec `set_time_limit(300)`.
3. Le poster-bet enchaîne plusieurs paquets ; le **cron** vide le reste.

## CRON obligatoire pour fiabiliser

Ajouter une tâche **toutes les 1 à 2 minutes** (hPanel / cPanel / crontab) :

```bash
* * * * * wget -q -O /dev/null "https://stratedgepronos.fr/cron/process-notif-queue.php?token=VOTRE_TOKEN"
```

**Token** : dans `includes/db.php`, ajoute par exemple :

```php
define('NOTIF_CRON_TOKEN', 'colle-ici-une-chaine-longue-aleatoire');
```

Sinon le script utilise automatiquement  
`hash('sha256', SECRET_KEY . 'NOTIF_CRON_V1')` — tu peux l’afficher une fois en local avec :

```php
echo hash('sha256', SECRET_KEY . 'NOTIF_CRON_V1');
```

## Vérification

- Après un post, consulter `notif_debug.log` à la racine de `public_html`.
- Si `reste_file > 0`, le cron doit tourner jusqu’à `pending_end=0`.

## Table SQL

Créée automatiquement au premier envoi (`notif_queue`). Sinon :

```sql
CREATE TABLE IF NOT EXISTS notif_queue (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id VARCHAR(32) NOT NULL,
  membre_id INT UNSIGNED NOT NULL,
  email VARCHAR(255) NOT NULL,
  nom VARCHAR(120) NOT NULL DEFAULT '',
  bet_type VARCHAR(64) NOT NULL DEFAULT '',
  bet_titre VARCHAR(500) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  sent_at DATETIME NULL DEFAULT NULL,
  last_error VARCHAR(500) NULL,
  KEY idx_pending (sent_at, id),
  KEY idx_batch (batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
