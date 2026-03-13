# CRON — Sauvegarde quotidienne des comptes membres

## Script

- **Fichier** : `backup-membres.php`
- **Tables sauvegardées** : `membres`, `abonnements`, `push_subscriptions`
- **Dossier de sortie** : `public_html/backups/` (accès web refusé)
- **Conservation** : 30 jours (les fichiers plus anciens sont supprimés automatiquement)

## Crontab (tous les jours à 3h du matin)

À ajouter sur le serveur avec `crontab -e` :

```bash
0 3 * * * /usr/bin/php /chemin/vers/public_html/cron/backup-membres.php
```

Remplace `/chemin/vers/` par le chemin réel (ex. sur Hostinger : `~/domains/stratedgepronos.fr/public_html/cron/backup-membres.php` ou le chemin indiqué dans le panel).

Pour connaître le chemin de PHP :

```bash
which php
```

## Alternative : CRON par URL (hébergeur sans accès SSH)

Si ton hébergeur propose une tâche planifiée par URL :

1. URL à appeler :  
   `https://stratedgepronos.fr/cron/backup-membres.php?key=backup_membres_TA_CLE_SECRETE`  
   (remplace `TA_CLE_SECRETE` par la valeur de `SECRET_KEY` dans `includes/db.php`)

2. Fréquence : une fois par jour (ex. 03:00).

⚠️ La clé ne doit pas être partagée. Tu peux aussi définir une clé dédiée dans le script au lieu d’utiliser SECRET_KEY.

## Restauration

1. Ouvre phpMyAdmin (ou ton outil de gestion de BDD).
2. Sélectionne la base.
3. Onglet **Importer** : choisis le fichier `backups/membres_AAAA-MM-JJ.sql` (ou copie son contenu dans l’onglet SQL).

Les tables `membres`, `abonnements`, `push_subscriptions` seront recréées et remplies à partir de la sauvegarde.
