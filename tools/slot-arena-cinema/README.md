# CROWN v3 — production de la cinématique

Film original rendu avec Blender 4.3.2 (Cycles CPU), puis monté avec FFmpeg.
Les noms, le parcours et les scores du vainqueur restent des éléments HTML dynamiques.

## Reproduire les images

Prérequis : Blender 4.3.2, Python 3 et FFmpeg avec libx264/minterpolate.
Les fichiers sont écrits à côté des scripts, ou dans `CROWN_RENDER_DIR` si défini.

```bash
for shot in macro approach orbit hero; do
  blender -b -t 8 --python render_film.py -- "$shot" animation
  python encode_shot.py "$shot"
done
```

Assembler `macro.mp4` (4 s), `approach.mp4` (5 s), `orbit.mp4` (5 s),
puis `hero.mp4` (20 s) avec le démultiplexeur concat de FFmpeg, copie des flux
et `-movflags +faststart`. Le fichier livré est `crown-film-v3.mp4` : H.264,
1280 × 720, 24 images/s, 34 secondes, sans piste audio incorporée.

Pour l'affiche : `blender -b -t 8 --python render_film.py -- hero still`,
puis convertir `render/hero-still.png` en JPEG de qualité 92.

## Bande originale et droits

« Titan » de Scott Buckley, licence CC BY 4.0 :
https://www.scottbuckley.com.au/library/titan/

Le fichier `crown-score-v3.mp3` contient un extrait à partir de 141,8 secondes,
d'une durée de 34 secondes, avec fondu entrant de 2,2 s, fondu sortant de 3 s,
normalisation loudnorm I=-16 / TP=-1.5 / LRA=11 et encodage MP3 192 kbit/s.
L'attribution visible et le lien de licence sont obligatoires lors de toute réutilisation.
Voir `public_html/tournoi-slots/assets/ATTRIBUTION.txt`.

Le lecteur utilise l'horloge vidéo pour les titres et la musique Web Audio.
Il suspend la musique pendant la mise en mémoire tampon et reprend au même point.
Les tests `tests/slot-arena/test_cinema.cjs` couvrent ces comportements ainsi que
la fermeture, le rejeu, les animations réduites et les erreurs de lecture.
