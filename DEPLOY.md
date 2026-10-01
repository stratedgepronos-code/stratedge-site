# Déploiement StratEdge

## Images : ne plus les perdre après un déploiement

Le dossier `public_html/uploads/` est **ignoré par Git** (`.gitignore`). Si votre déploiement fait un `git pull` ou un clone propre, les fichiers uploadés peuvent disparaître.

### Solution en place

1. **Migration SQL** (à exécuter une fois dans phpMyAdmin) : voir `public_html/migrate.sql` — sections « image_data » et « locked_image_data ». Cela ajoute les colonnes de sauvegarde des images en base.

2. **Sauvegarde automatique** : à chaque nouveau bet posté, l’image est enregistrée sur le disque **et** en base (blob). Les URLs du site pointent vers `restore-image.php`, qui sert l’image depuis le disque ou, si le fichier manque, depuis la BDD.

3. **Après un déploiement** : aller dans **Panel admin → Restauration images** (`/panel-x9k3m/upload-restore.php`) :
   - Si les images sont encore sur le serveur : cliquer sur **« Sauvegarder tout en BDD »**.
   - Si les images ont disparu mais la BDD contient les blobs : cliquer sur **« Restaurer tout BDD → disque »**.

### À ne pas faire

- Ne pas supprimer le dossier `public_html/uploads/` lors du déploiement (le script peut recréer les fichiers depuis la BDD, mais il vaut mieux garder le dossier).
- Ne pas réintégrer `public_html/uploads/` dans Git (les binaires ne doivent pas être versionnés).
