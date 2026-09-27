# StratEdge Live V4 — colonnes du tableau Packball

L'import avant-match reconnaît les nouveaux groupes GPT / GPT - 2 de 31 et 37 choix (40 et 46 colonnes CSV), vérifiés sur les exports du 28/09/2026, ainsi que les anciens groupes A/B (49/39 colonnes compactes ou 72/47 après expansion). Le dictionnaire exact figure dans `PROMPT_ANALYSTE_V4.md`. La colonne 32 du groupe GPT peut être corrigée en paire Domicile-Extérieur ; si elle reste Global, elle est conservée sans attribution aux équipes et cette limite est signalée. Le dossier GPT utilise **Importer l’analyste**, schéma `stratedge.context.v4`.

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

Dans Tampermonkey, mettre à jour **le script existant** avec `live90-packball.user.js` version 4.0.1 ; conserver le nom/namespace et le stockage du token. Éviter de créer deux collecteurs actifs en parallèle.

Le script couvre désormais toutes les routes de packball.com pour survivre à la navigation interne ; il n’envoie des données que depuis les pages de matchs. Le badge est réattaché si la page le retire. La collecte passe toutes les 30 secondes. Si Chrome bloque l’exécution des scripts utilisateur, la page ne peut pas activer cette permission à sa place.

Le collecteur lit les lignes réellement chargées dans le DOM, même hors écran ; il ne peut pas lire des rencontres non chargées ou virtualisées. Le compteur reçu permet de contrôler ce point. PC éteint, navigateur fermé, veille ou restriction des onglets : plus de données fiables, donc plus de déclenchement. Le serveur continue de conserver l’historique et d’indiquer la coupure.

## Correspondance de la capture du 27/09 à 22:57

Ordre des 14 colonnes choisies, après les colonnes fixes d'identité et de cotes 1x2 :

| Position visible | Colonne | Destination | Exemple Fortaleza / Athletic Club |
|---|---|---|---|
| 1 | Plus buts équipe domicile (+1) | `quotes`: team_goals, FT, h, over | ligne 2,5 ; cote 1,90 |
| 2 | Plus buts équipe visiteuse (+2) | `quotes`: team_goals, FT, a, over | ligne 2,5 ; cote 3,75 |
| 3 | Buts | `stats.goals`, contrôle du score principal | 2–2 |
| 4 | Total des tirs | `stats.shots` | 7–5 |
| 5 | Tirs cadrés | `stats.sot` | 4–3 |
| 6 | Possession | `stats.possession` | 66–34 |
| 7 | Cartons jaunes | `stats.yellow_cards` | 0–0 |
| 8 | Cartons rouges | `stats.red_cards` | 0–0 |
| 9 | Cartons jaunes-rouges | `stats.second_yellow` | inconnu, pas zéro |
| 10 | Fautes | `stats.fouls` | 8–9 |
| 11 | Total des tirs 10 dernières minutes | `ind10.shots10` | 2–1 |
| 12 | Tirs cadrés 10 dernières minutes | `ind10.sot10` | 2–1 |
| 13 | Total des tirs 5 dernières minutes | `ind5.shots5` | 0–1 |
| 14 | Tirs cadrés 5 dernières minutes | `ind5.sot5` | 0–1 |

La première valeur de chaque paire appartient à l'équipe à domicile. Les positions
ci-dessus décrivent la capture ; elles ne sont pas codées en dur. Le collecteur lit
les libellés d'en-tête (y compris les infobulles imbriquées) et distingue les fenêtres
5/10 minutes. Les cotes sont associées à leur libellé via la classe de colonne Packball.
Un nombre différent de cellules et d'en-têtes, un libellé absent, un doublon de champ
ou une contradiction entre le score principal et la colonne Buts interdit le signal
sur le relevé concerné. Le JSON exporté inclut `column_map` et la destination `target`
de chaque cellule pour vérifier la correspondance réelle.

Les valeurs de cette capture servent de test de lecture, pas de sélection de pari.
