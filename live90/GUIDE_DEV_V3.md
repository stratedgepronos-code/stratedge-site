# StratEdge · SEUIL 90 V3 — scénarios locaux sans API OpenAI

## Changement demandé

Analyse avant-match dans une conversation ChatGPT → dossier JSON → import privé sur StratEdge → surveillance locale des conditions → alertes sur le site et Telegram. Aucun appel OpenAI sur le serveur, ni avant-match ni live. Aucune clé OpenAI nécessaire dans ce module. L’abonnement de la conversation, Packball et l’hébergement restent indépendants.

La V3 remplace le moteur V2 ; elle ne réutilise ni les anciennes règles A/B/C/D ni les seuils fixes du moteur V1/V2 pour émettre des signaux. Les seuils proviennent de chaque scénario importé. Les quelques tests historiques restent isolés dans tests/legacy_evaluate.py et ne sont pas chargés en production.

## Installation / mise à jour

PHP 8.1+, PDO SQLite, Python 3.10+, systemd et fonction PHP proc_open autorisée. L’import appelle un validateur Python local avec un tableau d’arguments fixes ; le dossier transite sur stdin, sans commande shell construite depuis le JSON. PHP-FPM doit autoriser proc_open (le contrôle CLI ne suffit pas si ses réglages diffèrent).

1. Décompresser le pack. Lire et comparer les éventuelles modifications spécifiques du serveur avant d’écraser la V2.
2. Sauvegarder /var/lib/stratedge/live90.sqlite de façon cohérente avec SQLite (backup, pas une copie du seul fichier pendant des écritures WAL), ainsi que la configuration. update.sh sauvegarde le code et les unités, pas la base.
3. Module déjà installé : `sudo bash update.sh /var/www/stratedgepronos.fr/public_html`.
4. Première installation seulement : `sudo bash install.sh /var/www/stratedgepronos.fr/public_html`.
5. Mettre à jour le userscript Tampermonkey avec live90-packball.user.js (version 3.0.0). Vérifier le token de collecte.
6. Activer le moteur : `sudo systemctl enable --now seuil90-live.service`.
7. Le timer seuil90-context est désactivé par update.sh ; son worker est aussi remplacé par un programme sans réseau. Ne pas le réactiver. Une ancienne clé configurée ne déclenche plus aucun appel depuis ce module.
8. Site privé : `/panel-x9k3m/slate/live90/`. L’authentification requireSuperAdmin et la protection CSRF sont conservées.
9. Pour Telegram : conserver TELEGRAM_BOT_TOKEN et TELEGRAM_CHAT_ID du serveur, et régler SE90_TELEGRAM=1 dans /etc/stratedge/live.env, puis redémarrer seulement seuil90-live. Aucun token ne doit apparaître dans le dossier ChatGPT.

Les services d’autres modules ne sont pas modifiés. La V3 ne garantit pas l’absence de consommation OpenAI par ces autres modules.

## Parcours quotidien

- Importer les profils CSV Packball comme auparavant si nécessaire, puis « Exporter pour ChatGPT ». L’export contient les identifiants Packball, les équipes, heures ISO avec fuseau et moyennes. Il fournit une base à compléter, pas des scénarios inventés.
- Dans la conversation dédiée, coller PROMPT_CONVERSATION_SCENARIOS.md et joindre l’export. On peut partir directement des CSV et d’une liste d’IDs vérifiés ; ne jamais inventer un identifiant absent.
- ChatGPT fournit un dossier au format de scenarios-exemple.json. Les matchs sans scénario étayable peuvent rester avec scenarios: [].
- Sur le site : « Importer les scénarios », choisir le JSON, vérifier le résumé puis valider. L’import enregistre le profil et le plan ensemble, en une transaction. Une erreur rejette tout le fichier. Un fichier identique est reconnu sans doublon.
- Les mises à jour sont autorisées avant le coup d’envoi uniquement. Pour corriger un match d’un dossier comportant déjà des matchs commencés, demander un nouveau dossier contenant seulement les matchs encore à venir.
- Après un nouvel import CSV, le dossier précédent n’est plus utilisé pour ce match : réimporter un dossier cohérent avant le coup d’envoi.
- Les scénarios restent hypothétiques. Conserver le mode observation et vérifier les alertes avant toute utilisation.

## Scénarios et marchés réellement implémentés

- `pressure` : fenêtre de minutes, relation au score, seuils récents et comparaison aux moyennes avant-match.
- `conceded_early` : transition observée 0–0 → 0–1 pour l’équipe ciblée, avant une minute limite ; puis réaction sur une fenêtre de 5 ou 10 minutes entièrement postérieure au but. L’équipe doit rester menée d’un but. L’heure du but est approximée conservativement par le premier relevé qui le constate, jamais inventée.
- Marchés : over total de buts FT ou HT ; over buts d’une équipe FT ou HT. Ligne = buts déjà marqués dans le marché + 0,5. Les lignes entières/asiatiques, under, BTTS, prochain but, corners et cartons ne sont pas implémentés.
- HT signifie première mi-temps, avec déclenchement au plus tard à 44′. FT signifie total sur le match ; ce n’est pas la deuxième mi-temps seule. Arrêts de jeu exclus pour éviter de confondre 45+ avec 46′.
- Une condition de cadrés récents et une condition activity_ratio ou sot_ratio sont obligatoires. Le ratio compare un cumul live à la moyenne avant-match multipliée par minute/90 ; ce repère linéaire n’est pas une probabilité.
- Une cote doit correspondre exactement au marché, à la période, à l’équipe et à la ligne. Vérification des libellés explicites, aucune conversion entre prochain but et but avant la pause. Une colonne inconnue reste non mappée.
- Les cotes sont bet365 via Packball. Elles ne prouvent pas la disponibilité du pari chez Stake. Aucun placement de pari n’est automatisé.

## Contrôles et limitations

Dossier préparé depuis moins de 24 heures, import avant-match, ID exact + contrôle des noms et de l’heure live. Au moins 8 matchs par équipe, statistiques complètes non négatives. Sources HTTPS déclarées avec date de consultation : le validateur contrôle la structure, pas leur véracité. Les compositions et absences intervenues après la préparation ne sont pas recherchées automatiquement ; réviser dans la conversation et réimporter avant le match si nécessaire.

Deux relevés concordants espacés de 30 à 100 secondes, minute progressant de 1 à 2, score inchangé, statistiques et cote fraîches (120 s). Pas de scénario en cas d’expulsion connue, compteur absent/incohérent, marché ambigu ou donnée périmée. Après une correction de score à la baisse, le scénario de but précoce est annulé. Une alerte déjà envoyée n’est pas rétractée automatiquement.

Déduplication persistante : au plus un signal par match/marché/période/équipe pour cette V3, même après redémarrage. Des marchés différents sur le même match peuvent produire plusieurs observations corrélées. Une requête Telegram ambiguë est marquée uncertain et n’est pas réenvoyée aveuglément.

La collecte reste celle du DOM Packball : elle lit les lignes présentes dans la page, même hors écran, mais ne peut pas garantir la couverture des lignes virtualisées/paginées qui ne sont pas dans le DOM. En recette réelle, comparer le nombre de matchs collectés au nombre attendu. Ce pack ne prétend pas avoir résolu une éventuelle virtualisation Packball ou ses accès 401.

Le mapping des nouveaux marchés repose sur les titres français explicites des colonnes et doit être vérifié sur un vrai live. Aucun test local ne remplace cette recette. Si l’option « buts équipe première mi-temps » n’existe pas sur Packball, ce scénario attend une cote et n’alerte pas : ne pas lui substituer un marché prochain but.

## Historique et règlement

Une alerte archive le scénario exact, les mesures, la source du dossier et la cote observée. Les anciens signaux restent visibles. Le formulaire demande le score final pour FT ou le score de la première mi-temps pour HT. Pour un marché équipe, seul le score de l’équipe ciblée compte. Le règlement manuel exige une source ; les prolongations ne doivent pas être incluses dans FT. Paris annulés/interrompus : laisser pending et traiter selon les règles du bookmaker, aucun verdict inventé.

## Recette avant mise en service

- Les scripts install/update exécutent les tests Python et le lint PHP avant copie. Aucun déploiement n’a été effectué depuis la conversation.
- Tests locaux : validation atomique du dossier, transitions de score, annulation, périmption, correspondance du marché, absence d’appels OpenAI, déduplication et Telegram simulé.
- Vérifier sur le serveur : PHP-FPM/proc_open, authentification, import du dossier (ne pas utiliser les données fictives de l’exemple pour miser), collecte des deux fenêtres et des cartons, fonctionnement d’un marché HT et d’un marché FT, arrêt effectif du timer IA et réception dans le canal Telegram voulu.
- Aucun appel payant, aucun vrai envoi Telegram et aucune validation de rentabilité n’ont été réalisés dans les tests de livraison.

## Correctifs 3.0.1 (à conserver dans toute version ultérieure)

**Page : aucun fichier statique chargé depuis `assets/`.** Sur ce serveur, le bloc nginx `/panel-x9k3m/` est un bloc de préfixe sans `^~` : les règles de cache par expression régulière l'emportent et répondent 404 à tout `.css` ou `.js` placé sous le panel. `index.php` lit donc `style.css`, `app.js` et `context.js` côté serveur et les intègre dans des balises `<style>` et `<script>`, en neutralisant toute séquence `</style` ou `</script` du contenu. Les fichiers de `assets/` restent la source. Ce défaut a été réintroduit par la V2 puis par la V3 : `tests/test_page.py` échoue désormais si la page référence à nouveau `assets/` ou oublie d'intégrer un fichier.

**Telegram : `deliver()` distingue refus et incertitude** (`server/scenario_engine.py`).
- `sent` : réponse JSON `ok: true`.
- `failed` : réponse certaine qu'aucun message n'est parti — erreur HTTP (400 chat introuvable, 401 bot invalide…), réponse JSON `ok: false`, ou configuration absente (aucune requête tentée).
- `uncertain` : timeout, rupture réseau ou réponse illisible — le message a pu partir. Jamais renvoyé automatiquement : `deliver()` ne reprend que les signaux `queued`.

Sept tests couvrent ces cas dans `tests/test_scenarios.py`, chacun sur deux cycles moteur pour vérifier l'absence de renvoi. Sur le code V3 d'origine, les tests 400, 401 et configuration absente échouent.

## Correctif 3.0.2 — sidebar du panel

La console inclut la sidebar partagée (`admin/sidebar.php`), comme les autres pages admin, avec `$pageActive = 'live90'`. La sidebar est en position fixe à gauche : le contenu est enveloppé dans `<div class="main">`, qui porte la marge de 240 px. Le code de la sidebar est capturé à part : en cas d'échec, la console s'affiche sans elle et la cause figure en commentaire HTML.

L'entrée « 📡 Live » de la sidebar se trouve dans `admin/sidebar.php`, hors du module : `update.sh` n'y touche pas. Contrôlé : aucun identifiant, fonction globale ou classe CSS commune entre la console et la sidebar ; le rail de la console n'est pas fixe et se place à droite de la sidebar. `tests/test_page.py` vérifie l'inclusion, l'enveloppe `.main` et la tolérance à l'échec.

## Correctif 3.0.3 — import de plusieurs dossiers

Le champ d'import accepte plusieurs fichiers JSON. Chaque dossier reste un envoi distinct, validé en entier par le serveur : pas de fusion, qui ferait perdre sa date de préparation et sa détection de doublon. Envoi du plus ancien au plus récent (`generated_at`) : un match présent dans deux dossiers prend le plus récent, et l'aperçu le signale. Un fichier illisible bloque la validation jusqu'à son retrait. Si le serveur refuse un dossier, ceux déjà passés restent importés et la fenêtre détaille le résultat par fichier. Parcours testé dans un DOM (jsdom) : deux dossiers dans le désordre, refus du second, fichier illisible, fichier unique.
