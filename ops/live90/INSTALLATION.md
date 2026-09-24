# SEUIL 90 · Live Console — 1.0.0

Module d’observation indépendant pour le serveur PHP/Python StratEdge décrit dans la conversation. Une interface de production, un protocole expérimental : ce n’est pas un modèle de probabilité calibré et sa rentabilité n’est pas démontrée.

## Contenu et aperçu

Ouvrir `APERCU.html` dans un navigateur : démonstration interactive, données fictives clairement signalées, sans connexion ni envoi. Navigation Live/Historique, détail de match et aperçu d’import disponibles. Les écritures sont désactivées dans cet aperçu. Polices Google facultatives, polices système de secours.

- `public/admin/slate/live90/` : console privée, import CSV, détail, historique et résultat manuel.
- `public/api/live90/` : récepteur sécurisé et initialisation SQLite.
- `live90-packball.user.js` : collecteur Tampermonkey indépendant.
- `server/engine.py` : moteur d’observation indépendant, boucle 30 secondes.
- `server/seuil90-live.service` : nouveau service systemd, jamais l’ancien moteur.
- `prematch-modele.csv` : en-tête du format normalisé facultatif ; le véritable export Packball peut être associé manuellement dans l’interface.
- `tests/` : tests du protocole et de son intégration SQLite.

## Installation sur le serveur

Prérequis : accès SSH sudo, PHP 8.1+ avec PDO SQLite dans PHP CLI **et PHP-FPM**, Python 3.10+, systemd. L’authentification existante doit fournir `requireSuperAdmin()` dans `public_html/includes/auth.php`.

1. Décompresser l’archive dans un dossier privé du serveur, hors du répertoire web.
2. Dans ce dossier, lancer :

```bash
sudo bash install.sh /var/www/stratedgepronos.fr/public_html
```

L’installateur vérifie PHP, syntaxe et tests avant de copier. Il crée de nouveaux dossiers et refuse de les écraser s’ils existent déjà. Il ne touche pas aux anciens fichiers, à leur base, à leurs services ni au contenu de `live.env`. Si une installation échoue après une copie, vérifier l’erreur : ne pas supprimer aveuglément les dossiers pour relancer.

3. Ouvrir, en étant connecté comme super administrateur :

`https://stratedgepronos.fr/panel-x9k3m/slate/live90/`

Le chemin physique reste `public_html/admin/slate/live90/`. La réécriture `/panel-x9k3m/` doit être celle de ton serveur ; si elle ne couvre pas les sous-dossiers, ton administrateur doit adapter la configuration web. Aucun accès public n’est ajouté. La page n’est pas encore ajoutée au menu partagé : utiliser le lien direct.

4. Installer `live90-packball.user.js` dans Tampermonkey. Désactiver l’ancien userscript pour éviter de confondre les collectes. Dans le menu du nouveau script, « S90 · Définir le token », renseigner localement la valeur existante `SE_LIVE_TOKEN`. Ne pas transmettre cette valeur dans la conversation.
5. Ouvrir Packball. Le badge doit afficher le nombre de relevés reçus. Le serveur doit répondre HTTP 200 avec `ok:true`. La console affiche l’âge des données ; au-delà de 120 secondes, elles sont périmées.
6. Importer les statistiques d’avant-match avant le coup d’envoi, puis lancer :

```bash
sudo systemctl enable --now seuil90-live.service
sudo systemctl status seuil90-live.service --no-pager
```

Si l’ancien moteur tourne encore, son arrêt est distinct : `sudo systemctl stop stratedge-live.service`. Cette commande arrête ses anciennes alertes ; elle ne supprime pas son historique. Les anciens timers ne sont pas nécessaires au nouveau module.

## Configuration et Telegram

Le récepteur réutilise `SE_LIVE_TOKEN` dans `/etc/stratedge/live.env`. La base est distincte : `/var/lib/stratedge/live90.sqlite`. Pour changer son chemin, ajouter `SE90_DB=/var/lib/stratedge/autre-live90.sqlite` au même fichier ; garder le chemin dans `/var/lib/stratedge` pour le confinement systemd.

Les signaux apparaissent sur la page dès qu’ils sont créés. Pour activer leur envoi Telegram, conserver les valeurs existantes `TELEGRAM_BOT_TOKEN` et `TELEGRAM_CHAT_ID`, ajouter dans le fichier d’environnement :

```ini
SE90_TELEGRAM=1
```

Puis `sudo systemctl restart seuil90-live.service`. Chaque message porte le préfixe OBSERVATION. Les signaux précédemment créés avec les envois désactivés ne sont pas expédiés rétrospectivement.

L’état Telegram reste visible dans l’historique : `disabled`, `queued`, `sending`, `sent`, `failed`, `uncertain`, `expired`. Une réponse réseau incertaine n’est pas renvoyée automatiquement, pour éviter les doublons. Ce compromis privilégie l’absence de doublons ; il peut perdre une notification et demande un contrôle de l’historique. Aucun message n’a été envoyé depuis l’environnement de développement.

## Avant-match : import obligatoire

Choisir le CSV puis associer les colonnes. Si une cellule contient une paire telle que `12,4-10,7`, sélectionner cette même colonne pour domicile et extérieur : le parseur prend chaque côté. Les colonnes individuelles sont acceptées. Les moyennes doivent correspondre à des matchs complets et être comparables entre équipes. Ne pas mélanger moyenne d’équipe et moyenne totale de match.

Champs obligatoires : identifiant Packball, coup d’envoi, deux équipes, nombre de matchs de chaque échantillon, buts marqués/encaissés moyens par équipe, tirs et tirs cadrés moyens par équipe. Cotes avant-match O/U 2,5 facultatives : elles sont conservées et affichées mais ne produisent pas de probabilité propre ni d’avantage calculé dans cette V1.

L’ID se lit dans la console sur la ligne du match, même avant son début si Packball l’affiche. Si le CSV ne le contient pas, le saisir dans l’aperçu. La jointure n’est jamais devinée d’après les noms. Le moteur contrôle aussi les noms (accents/casse normalisés) et l’heure (tolérance 15 min). Les noms doivent donc correspondre à Packball.

Dates acceptées : `JJ-MM-AAAA HH:MM`, `JJ/MM/AAAA HH:MM`, ou ISO avec fuseau. Les deux premiers formats utilisent le fuseau du navigateur, annoncé dans l’import. Ne pas importer le CSV de résultats à cet endroit. Le serveur rejette tout profil importé après le coup d’envoi : aucun historique antidaté. Un mauvais profil peut être corrigé par un nouvel import **avant** le match ; les versions restent conservées.

## Colonnes Packball nécessaires

Garder le total sous 40. La collecte ne dépend plus d’une sélection rectangulaire à l’écran.

- Cotes en direct (bet365) → Total de buts – Première ligne → Plus Buts – Première ligne et Moins Buts – Première ligne.
- Statistiques Temps plein → Total des tirs, Tirs cadrés, Cartons rouges, Cartons jaunes-rouges.
- Statistiques 10 dernières minutes → Total des tirs, Tirs cadrés.
- Statistiques 5 dernières minutes → Total des tirs, Tirs cadrés (affichage complémentaire).
- Identité du match, minute et score sont lus dans les colonnes structurelles.

Possession, pression et ExG 5/10 sont conservés si leurs intitulés sont reconnus, mais ne pilotent pas ce protocole. ExG Packball prospectif ≠ xG historiques de tirs.

Le mapping des deux prix O/U utilise les classes `inp-7/8` **et** un contrôle de l’intitulé. Une colonne ambiguë est bloquée. Une variation du DOM pourra exiger une adaptation : exporter alors le relevé depuis le menu Tampermonkey. Le fichier ne contient pas le token.

## Ce que fait le protocole V1

Cette version vérifie toute la chaîne et collecte des hypothèses testables. Ce n’est pas la version finale du moteur prédictif souhaité.

- Avant-match enregistré avant le début, au moins 8 matchs par équipe.
- Repère d’activité : `(tirs moyens domicile + tirs moyens extérieur) × minute / 90`. C’est un repère linéaire exploratoire, pas une courbe temporelle apprise.
- Environnement de buts : moyenne des totaux marqués+encaissés des deux équipes, au moins 2,5. Aucun λ de l’ancien moteur n’est utilisé.
- Minute 55–75, total actuel au plus 2, écart de score au plus 1, aucune expulsion connue et compteurs de cartons disponibles.
- Tirs cumulés ≥ 1,15 fois le repère ; au moins 4 tirs et 1 cadré sur 10 minutes ; au moins 4 cadrés cumulés.
- Marché : Over `(buts actuels + 0,5)` sur le total FT. Cote observée 1,70–2,40, source bet365 via Packball. Le prix Stake n’est pas collecté.
- Deux relevés qualifiés espacés de 30–100 secondes, même score, minute en progression, reçus depuis moins de 120 secondes.
- Un seul signal par match et version de modèle. Pas de combiné, placement de pari, mise proposée, probabilité ou EV inventée.

Ces seuils ne sont pas ajustés ni validés sur un historique. La cote minimale 1,70 est un critère du protocole, **pas une cote de rentabilité calculée**. Les résultats doivent être évalués sur de nouvelles journées, sans sélectionner rétrospectivement les seuls matchs gagnants. Toute évolution devra changer VERSION et conserver un échantillon hors entraînement.

## Résultats et limites à connaître

Le règlement est volontairement **manuel et tracé** : saisir le score final confirmé et sa source depuis le signal. Une perte de connexion n’est jamais considérée comme une fin de match. Seul le marché Over demi-ligne FT est pris en charge. Les litiges, interruptions et annulations exigent un traitement ultérieur : ne pas saisir un score fictif pour les solder.

Le collecteur lit toutes les lignes présentes dans le DOM, y compris hors écran. Il ne peut pas garantir les lignes jamais chargées, masquées par pagination ou virtualisées. Aucune API privée Packball n’est appelée et aucun 401 n’est contourné. L’ordinateur et l’onglet doivent rester actifs ; aucun watchdog Telegram de panne n’est fourni dans cette V1. La fraîcheur de réception ne prouve pas à elle seule que Packball a actualisé la cote : à contrôler en situation réelle.

Le module ne migre pas l’ancienne base et ne rejoue pas automatiquement ses données incomplètes. Pas de purge automatique, prévoir une sauvegarde SQLite cohérente et surveiller l’espace disque. L’API privée affiche les 500 derniers matchs et les 200 derniers signaux ; les compteurs de la console portent sur cette vue, pas sur un bilan global.

Validation réalisée : tests Python du protocole et de l’intégration SQLite ; syntaxe JavaScript ; tests ciblés des parseurs. Pas de PHP installé dans l’environnement de développement, donc lint PHP obligatoire dans l’installateur. Ni navigation sur le serveur privé, ni vrai flux Packball, ni Telegram vérifiés ici. La présentation doit être contrôlée sur le navigateur cible après installation.

## Retrait / retour arrière

Arrêter et désactiver uniquement `seuil90-live.service`, désactiver le nouveau userscript et revenir à l’ancienne page. L’ancien système n’est pas écrasé. Conserver la base Live90 pour l’audit.
