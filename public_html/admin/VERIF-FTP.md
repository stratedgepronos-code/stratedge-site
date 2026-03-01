# Vérification après envoi FTP — pourquoi les modifs ne s’affichent pas

## 1. Générer une **nouvelle** card
Le template est utilisé **au moment où tu génères** une card (bouton dans créer-card).  
Si tu regardes une **ancienne** card (déjà générée / enregistrée), elle garde l’ancien HTML.  
→ Va sur **Créer une card** → Tennis → Live → remplis et **génère une nouvelle card**.

## 2. Vérifier que le bon fichier est chargé (marqueur de version)
Après avoir généré une nouvelle card :
- Clic droit sur la card (ou dans l’iframe) → **Afficher le code source** (ou Inspecter).
- Recherche (Ctrl+F) : **StratEdge card template v9**
- Si tu vois ce commentaire → le **live-card-template.php** à jour est bien utilisé.
- Si tu ne le vois pas → le serveur utilise encore l’ancien fichier (mauvais chemin ou cache PHP).

## 3. Cache PHP (OPcache)
Sur beaucoup d’hébergements, PHP met en cache les fichiers (OPcache).  
Même après envoi FTP, l’ancienne version peut tourner.

À faire :
- **Espace d’hébergement** (cPanel, Plesk, etc.) : chercher « OPcache », « Redémarrer PHP », « PHP » → redémarrer PHP ou vider le cache.
- Ou contacter l’hébergeur : « Pouvez-vous vider le cache PHP / redémarrer PHP pour mon site ? »

## 4. Chemin FTP
Sur le serveur, le site est souvent servi depuis un dossier du type :
- `public_html/` ou `www/` ou `htdocs/`

Tu dois envoyer :
- **admin/live-card-template.php** → dans le dossier **admin/** de ce répertoire (celui qui contient **generate-card.php** et **creer-card.php**).
- **includes/claude-config.php** → dans le dossier **includes/** (à côté de **auth.php**, etc.).

Si tu as un doute : ouvre dans le navigateur  
`https://ton-site.fr/admin/generate-card.php`  
(en GET tu auras souvent une erreur « Données invalides », c’est normal).  
Si ça 404, ton **admin** n’est pas au bon endroit.

## 5. Cache navigateur
Après avoir vérifié tout ça : **Ctrl+Shift+R** (ou Cmd+Shift+R sur Mac) pour recharger sans cache, ou teste en **navigation privée**.

---

**Résumé :** Envoie les 2 fichiers par FTP → vide cache PHP si possible → génère une **nouvelle** card Tennis Live → dans le code source de la card, cherche **« StratEdge card template v9 »** pour confirmer que le bon template est utilisé.
