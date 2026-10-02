# Archive — Proposition « Le jeu, autrement. »

Proposition retirée le 2 octobre 2026 à la demande de l’utilisateur. L’interface publique a été restaurée à partir de la version `4c27b804fb72200e425d40ba93c2f43359b3967b` (fond sombre, accents roses, terrain graphique). Les corrections fonctionnelles, dont les liens avec ancre et source, sont conservées. Les visuels de cette proposition restent archivés, sans être chargés par les pages restaurées.

Direction artistique proposée le 2 octobre 2026. Remplace la première version éditoriale ; aucun changement de méthode de pari, de données, de tarifs ou de paiement.

## Composition

Ouverture photographique en plein cadre, titre grotesk associé à un italique de revue, support ivoire et accents roses. Accueil avec une publication principale et deux secondaires, carnet de méthode rose, registre des résultats et billets d’accès superposés. Même système sur journal, articles, résultats, méthode, offres, connexion et inscription.

Le contrôle « Le terrain / La lecture » ajoute des annotations pédagogiques à une scène illustrative. Il ne représente ni une analyse de match réel ni des données en direct. Accessible par boutons natifs au clavier et au toucher ; état annoncé via aria-pressed et texte aria-live. Sans JavaScript, la scène et les liens principaux restent disponibles, les contrôles inactifs sont masqués.

Animations : tracé tactique, léger zoom de photographie, révélations de sections, rotation des billets au survol et au focus. Pas de défilement capturé ni de curseur personnalisé. Pause globale et préférence système de réduction des mouvements. Le menu conserve son confinement de focus, Escape et inert mobile.

## Visuels originaux

Créés avec l’outil imagegen intégré, convertis en WebP pour le site :

- `public_html/assets/public-site/night-pitch.webp` — 1536 × 1024, 133 Ko.
- `public_html/assets/public-site/clay-court.webp` — 1086 × 1448, 486 Ko.

Prompt terrain : « Create a striking cinematic ultra-wide 3:2 landscape fine-art aerial photograph of a small football pitch at night, seen from directly above but slightly oblique, entire field visible on the RIGHT two-thirds of frame, left third very dark textured negative space to overlay large typography. The field runs horizontally across the image. A few tiny anonymous adult football players in ivory and faded pink kits, long powerful diagonal shadows cast by stadium floodlights from top right. Deep pine-green natural grass with visible mowing stripes and crisp white football lines, rich charcoal shadows. A little atmospheric mist on far border. Editorial analog 35mm grain, underexposed sports campaign photography, high contrast, sophisticated, natural and tactile, stadium surroundings almost black. Field center circle around 64 percent from left and 48 percent from top; goal at right edge, other goal around 35 percent from left. Camera 80 meters overhead. Believable physically coherent field markings and people. No graphics, text, logos, watermarks, glowing neon, sci-fi screens or futuristic architecture. Illustrative editorial artwork, not a real specific fixture. »

Prompt tennis : « Portrait 3:4 composition, sophisticated analog film sports photography. A vivid terracotta clay tennis court seen at steep angle from above, geometric crisp ivory baseline intersects diagonal dark net shadow. One luminous yellow-green tennis ball resting near the line in lower right; partial tennis racket cropped at far upper left. Brutalist minimal composition, real texture in clay and scuff marks, long shadows, bright late afternoon sunlight, strong geometry, muted red-orange clay with almost-black shadows. Huge uncluttered space. European independent sports magazine art direction, sensual physical surfaces, no gradient no glow no decorative graphics. No people, writing, typography, logo or watermark. Authentic imperfect photographic detail, not a 3D render. »

La légende d’accueil indique explicitement qu’il s’agit d’une scène illustrative. Les autres images illustrent des rubriques, jamais un résultat particulier. L’image principale est prioritaire ; les couvertures du journal sont différées. Aucun lecteur vidéo ni moteur WebGL ajouté.

Références de recherche, sans reprise de leurs créations : [David Alaba, Awwwards](https://www.awwwards.com/sites/david-alaba), [No Football Colors](https://nofootballcolors.com/), [No Football Colors, CSS Design Awards](https://www.cssdesignawards.com/sites/no-football-colors/49377/). Intérêt étudié : rapport image/typographie, personnalité éditoriale et transitions entre contenus sportifs.

## Vérifications

Recette PHP existante conservée : publications, versions, confidentialité, résultats et projection mémoire, prix, attribution agrégée et continuité inscription/offre. Ajout d’un contrôle des liens avec ancre pour conserver correctement leur source.

Recette navigateur à 1440, 390 et 320 px : débordements, erreurs JS/PHP, image et lecture tactique, boutons au clavier, liens des billets, accès sans JS, menu mobile, réduction des mouvements, filtres, catalogue et inscription, édition/publier/corriger en base isolée. Aucune action de paiement ni aucun message externe envoyé pendant les tests.
