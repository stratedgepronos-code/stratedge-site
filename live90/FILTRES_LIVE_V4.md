# StratEdge Live V4 — colonnes du tableau Packball

Conserve les deux exports avant-match actuels (groupes A/B reconnus : 49/39 colonnes compactes ou 72/47 après expansion des paires). Ils sont importés via **Importer Packball**. Le dossier GPT utilise désormais **Importer l’analyste**, schéma `stratedge.context.v4`.

Pour le tableau **Statistiques en direct**, les cases ci-dessous suffisent largement à rester sous 40 filtres. Ce sont des colonnes à afficher, pas des conditions éliminatoires sur les matchs. Conserve tous les matchs souhaités et vérifie la couverture du collecteur.

| Menu | Sous-menu / case | Utilisation |
|---|---|---|
| Statistiques Temps plein | Buts | Score et ligne de buts correcte |
| Statistiques Temps plein | Total des tirs | Rythme comparé à l’avant-match |
| Statistiques Temps plein | Tirs cadrés | Production offensive réelle |
| Statistiques Temps plein | Possession | Soutien de la lecture de pression |
| Statistiques Temps plein | Fautes | Cumul et variations sur 10 minutes |
| Statistiques Temps plein | Cartons jaunes | Ne pas signaler +0,5 si déjà atteint |
| Statistiques Temps plein | Cartons rouges | Toutes expulsions (y compris deuxième jaune selon ta confirmation) |
| Statistiques Temps plein | Cartons jaunes-rouges | Facultatif, information complémentaire |
| Statistiques 10 dernières minutes | Total des tirs | Activité récente de chaque équipe |
| Statistiques 10 dernières minutes | Tirs cadrés | Cadrés récents de chaque équipe |
| Statistiques 5 dernières minutes | Total des tirs | Dynamique courte |
| Statistiques 5 dernières minutes | Tirs cadrés | Confirmation de la dynamique courte |
| Cotes en direct (bet365) | Buts marqués par l’équipe à domicile → Ligne suivante Plus | But domicile avant la fin du match |
| Cotes en direct (bet365) | Buts marqués par les visiteurs → Ligne suivante Plus | But extérieur avant la fin du match |

Ces 14 cases sont identifiées dans ta liste. Les cotes **par équipe en première mi-temps** et **cartons par équipe +0,5** ne sont PAS présentes dans la liste de menus que tu as fournie. Il faut vérifier leur disponibilité réelle sur Packball. Si disponibles, afficher les colonnes dont le libellé explicite précise équipe, période et type de marché.

Le collecteur V4 sait reconnaître les libellés français explicites des buts par équipe HT/FT et des cartons par équipe FT. Il ne transforme pas :
- « Total de buts 1ère mi-temps » en buts d’une équipe ;
- « Qui marquera le prochain but » en but avant la pause ;
- « Total de cartons » en cartons d’une équipe ;
- des points de sanctions ou cartons jaunes uniquement en total de cartons.

Sans cote exacte, la dynamique est affichée **Cote à vérifier**, sans faux pari Telegram. On ne peut pas garantir les trois marchés automatiques avec les seules colonnes de ta liste actuelle. Il faut un relevé contenant les libellés et cotes manquants, ou une autre source autorisée pour ces marchés.

Les fautes sur 10 minutes sont calculées depuis les compteurs cumulés quand Packball ne propose pas cette fenêtre. Il faut alors environ 10 minutes de collecte continue pour obtenir le premier repère. Aucune valeur absente n’est transformée en zéro. Les ExG Packball « pour les prochaines minutes » ne sont pas utilisés comme xG de tirs observés.

## Redémarrer le collecteur

Dans Tampermonkey, mettre à jour **le script existant** avec `live90-packball.user.js` version 4.0.0 ; conserver le nom/namespace et le stockage du token. Éviter de créer deux collecteurs actifs en parallèle.

Le script couvre désormais toutes les routes de packball.com pour survivre à la navigation interne ; il n’envoie des données que depuis les pages de matchs. Le badge est réattaché si la page le retire. La collecte passe toutes les 30 secondes. Si Chrome bloque l’exécution des scripts utilisateur, la page ne peut pas activer cette permission à sa place.

Le collecteur lit les lignes réellement chargées dans le DOM, même hors écran ; il ne peut pas lire des rencontres non chargées ou virtualisées. Le compteur reçu permet de contrôler ce point. PC éteint, navigateur fermé, veille ou restriction des onglets : plus de données fiables, donc plus de déclenchement. Le serveur continue de conserver l’historique et d’indiquer la coupure.
