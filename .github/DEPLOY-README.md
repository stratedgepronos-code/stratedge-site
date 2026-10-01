# Déploiement automatique (StratEdge → Hostinger)

À chaque **push sur `master`**, le site est envoyé sur le serveur via **SSH + rsync** (comme pour Sortir Monde). Tu n’as rien à faire.

## Prérequis côté Hostinger

1. **SSH activé**  
   hPanel → **Avancé** → **Accès SSH** → activer l’accès SSH.

2. **`config-keys.php` (clés API)**  
   Fichier à la racine de `public_html/` sur le serveur, **hors Git**. Le workflow rsync le **laisse en place** (ne le supprime pas au deploy). Tu ne le remets qu’une fois ; copie depuis `config-keys.example.php` si besoin.

3. **Secrets GitHub** (déjà en place normalement)  
   Repo → **Settings** → **Secrets and variables** → **Actions** :
   - `FTP_HOST` : `178.16.128.35` (ou l’IP/host SSH indiqué dans hPanel)
   - `FTP_USER` : `u527192911`
   - `SSH_PASSWORD` : mot de passe SSH (souvent le même que le mot de passe FTP / compte Hostinger)

## Si le déploiement échoue

- **« No such file or directory » sur le chemin**  
  Le chemin distant peut être différent. Édite `.github/workflows/deploy-ssh.yml` et remplace la ligne `WEBROOT` par par exemple :
  ```yaml
  WEBROOT: /home/${{ secrets.FTP_USER }}/public_html
  ```
  (au lieu de `.../domains/stratedgepronos.fr/public_html`).

- **« Permission denied » ou « Connection refused »**  
  Vérifie que l’accès SSH est bien activé dans hPanel et que le port est **65002**.
