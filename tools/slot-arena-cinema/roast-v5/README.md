# Le sacre du chaos — CROWN v5

Deux versions de la finale de 34 secondes. Le joueur 0 (Alex, polo clair) utilise
`crown-roast-alex-v5.mp4` ; le joueur 1 (Marc, veste bleue) utilise
`crown-roast-marc-v5.mp4`. Le propriétaire de la slot championne détermine le film.

Les deux portraits WebP sont des caricatures générées à partir des références
fournies par l’utilisateur. Les photos originales ne sont pas publiées.

Reproduction depuis ce répertoire avec FFmpeg et Python 3 :

```bash
python render_roast.py alex
python render_roast.py marc
```

La source `crown-film-v4.mp4` contient déjà la musique et les plans Blender.
Les mouvements du portrait, fondus et particules sont calculés dans le fichier vidéo,
pour conserver une lecture native sans boucle audio indépendante. Les deux films
sont préchargés et conservés en mémoire pour les replays. Le poster adapté au gagnant
reste visible à la fin, lors d’une panne média ou avec les animations réduites.

Musique : « Titan » de Scott Buckley — https://www.scottbuckley.com.au/library/titan/
Licence CC BY 4.0 — https://creativecommons.org/licenses/by/4.0/
Extrait adapté de la version précédente, avec un bref creux de volume avant la révélation.
Les crédits restent affichés dans l’animation et dans l’aide du tournoi.
