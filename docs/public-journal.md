# StratEdge public — méthode et exploitation, version 1.0 (2 octobre 2026)

## Périmètre livré

Accueil éditorial avec terrain SVG, journal public, articles et dossiers de match, méthode, catalogue public, bilan des résultats, parcours de choix d’offre puis inscription. L’espace membre et les traitements de paiement existants conservent leur rôle. Les liens historiques vers les pages d’offres présentent le catalogue aux visiteurs anonymes. `historique.php` utilise le nouveau bilan ; les anciennes pages de détail et les données de bets restent conservées.

L’identité du front associe Manrope et Barlow Condensed, fond encre, papier clair et rose. Animations CSS/SVG, réaction au pointeur, progression de lecture, navigation mobile au clavier. Le réglage système de réduction des mouvements est respecté ; un bouton permet de suspendre les animations. Aucun compteur de fréquentation, témoignage ou résultat fictif n’est ajouté à la production.

## Journal et confidentialité

`/panel-x9k3m/journal.php` est réservé au super administrateur, y compris l’aperçu. Toutes les écritures passent par POST et CSRF. Un import Football Lab ne publie rien : le bouton « Préparer un article public » lit une analyse appartenant à l’utilisateur, extrait des champs autorisés et crée un brouillon descriptif. Ni les payloads complets, ni les clés d’API, ni les commentaires privés de contexte ne sont transférés.

Le brouillon nécessite une relecture, un contexte documenté et des sources avant de devenir une analyse publique. Les publications programmées sont filtrées côté serveur jusqu’à leur date UTC, saisie à l’heure de Paris. Elles apparaissent à la première consultation après cet horaire, sans cron et sans notification automatique. Le journal affiche les 200 dernières publications ; le sitemap accueille jusqu’à 45 000 entrées. Prévoir une pagination éditoriale au-delà de 200 articles.

Tables additives : `se_public_posts`, `se_public_revisions`, `se_public_counts`. Une publication parue conserve son URL, sa date, son marché, sa cote et ses horaires. Les corrections portent un motif et conservent les versions publiques antérieures. Les notes d’un brouillon non publié ne sont pas placées dans l’historique public. Un résultat peut être ajouté ensuite. Aucun effacement des analyses Lab ou des bets existants.

Une nouvelle sélection retenue doit être publiée avant le coup d’envoi, avec marché, cote, heure du relevé, horaire du match et sources. Une observation descriptive ne montre pas de pari validé. Une cote inscrite reste une cote relevée, jamais présentée comme un prix toujours disponible.

## Résultats

`Results::calculate` ne prend que `gagne`, `perdu`, `annule`. Le taux de réussite est gagnés / (gagnés + perdus). Le ROI simule une unité par pari non annulé, gain net de cote − 1 pour une victoire et −1 pour une défaite. Le prix des abonnements, les frais et les mises réelles ne sont pas inclus. Dès qu’une cote nécessaire manque, le ROI de toute la période est masqué. Une absence de données n’est pas présentée comme un taux nul.

Période : date de résultat si présente, sinon date de publication ; filtre glissant de 30 ou 90 jours ou tout l’historique. Catégories héritées : rôle Tennis ou catégorie tennis ; rôle Fun ; sinon Multisports. Les résultats sont renseignés par StratEdge, sans prétention de certification indépendante. Le journal n’est pas additionné au registre de bets, pour ne pas créer de doubles comptes.

## Mesure

Compteurs journaliers UTC par événement et source grossière (X, Telegram, etc.). Les UTMs sont transmis par les principaux liens publics ; une source de choix d’offre est conservée au plus une heure dans la session existante pour le retour d’inscription. Aucun nouvel identifiant publicitaire, IP ou referrer complet n’est enregistré. Pas de script tiers de suivi ajouté.

Ces compteurs mesurent des requêtes et des actions, pas des visiteurs uniques. Ils peuvent contenir des robots et rechargements ; ne pas en déduire un taux de conversion par personne. Une vraie création de compte est comptée après succès, jamais sur le faux succès du honeypot. Une panne du compteur ne bloque pas les pages.

L’admin montre séparément les abonnements Stripe au statut `validé` et les packs inscrits dans `credits_paris`, sur les 15 derniers jours. Il ne s’agit ni d’une attribution de ventes aux UTMs, ni de recettes nettes de remboursements. Les autres modes d’abonnement ne sont pas reconstitués ni inventés. Aucun traitement financier n’est modifié.

## Mise en ligne

`ops/install-public-journal.php` est exclusivement CLI. Il crée les tables si nécessaire et ajoute trois articles pédagogiques originaux, uniquement si leur slug n’existe pas, sans remplacer les modifications de la rédaction. Pas de paris, résultats ni témoignages créés par ce seed.

Le déploiement VPS exécute cet installateur, compare les octets des assets servis, contrôle les pages et un article publié. Les gates Live90 et FootyStats existantes restent actives. En cas de retour au code précédent, conserver les nouvelles tables pour garder le journal ; aucun rollback destructeur de données n’est prévu.

Recette : tests SQLite des règles de publication, protection des versions, calculs et liens ; serveur PHP isolé avec bases fictives et faux mailer pour les parcours navigateur. Contrôles desktop 1440, mobile 390 et 320 px, menu clavier, réduction du mouvement, brouillons/futurs invisibles, offres sans compte, choix conservé après inscription, droits super admin, CSRF et corrections. Les captures de recette portent des statistiques fictives dans la base isolée uniquement.

## Cadence des 15 prochains jours — publication humaine

Objectif au 17 octobre : un site lisible et actif, une première base de contenus et des signaux de lecture. Aucun délai de classement Google ni nombre de clients n’est garanti.

- J1–J2 : vérifier les pages déployées, relire les guides, choisir une analyse Lab et compléter son contexte et ses sources ; vérifier les sitemaps dans Search Console si l’accès existe.
- J3–J5 : publier un dossier de match argumenté et son débrief après résultat. Copier un extrait vers les canaux détenus (X/Telegram), avec le lien fourni, puis publier manuellement.
- J6–J8 : documenter une abstention ou une limite d’échantillon ; répondre aux questions récurrentes par un guide utile. Examiner les lectures d’articles et les consultations d’offres.
- J9–J12 : publier un deuxième dossier sourcé et un débrief ; adapter les titres et les appels vers les offres à partir des retours réels, sans réécrire une sélection après coup.
- J13–J15 : bilan de la période et de la qualité des données, comparaison des sources de consultation, contrôle des achats dans leurs registres. Conserver ce qui apporte des lectures et des échanges qualifiés.

Le calendrier ne force aucun pari et ne programme aucun envoi à des tiers. Les boutons de partage copient du texte ; ils n’envoient ni email, ni message Telegram, ni publication X.
