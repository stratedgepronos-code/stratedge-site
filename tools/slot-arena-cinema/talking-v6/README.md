# CROWN v6 — Le champion prend la parole

Alex et Marc : bouche animée par morphing local entre deux images générées, guidée par l’énergie de la réplique. Petit mouvement de tête, à partir de 15,5 s. Il s’agit d’une animation de caricature, pas d’une reconstruction phonétique réaliste.

Réplique : « Ouaais ! C’est moi qui ai gagné ! ». Voix française synthétique Piper Tom medium, débit ralenti lors de la synthèse (length_scale 1.45), Marc transposé de +6 %. Aucune voix personnelle clonée. Les WAV de production sont inclus pour conserver exactement le rendu.

Provenance du modèle : https://huggingface.co/rhasspy/piper-voices/tree/main/fr/fr_FR/tom/medium . Model card : dataset / modèle AGPLv3, https://git.bksp.space/Tjiho/French-tts-model-piper . Le modèle et le moteur ne sont pas distribués dans ce dépôt ; seul l’audio généré est utilisé.

La musique Titan — Scott Buckley, CC BY 4.0, et le film source CROWN v4 sont conservés. Musique abaissée de 15,2 à 19,5 s pour laisser passer la réplique. Les mouvements, la voix et la musique sont encodés ensemble : aucune animation ni synthèse audio exécutée en direct dans le navigateur.

Reproduction : Python avec numpy, opencv-python-headless ; FFmpeg avec libx264 et AAC. Depuis ce dossier, exécuter `python animate_face.py alex`, `python render_talk.py alex`, puis les mêmes commandes avec `marc`. Les portraits ouverts viennent de `../roast-v5/` ; les portraits fermés et les WAV sont ici. Le film de base vient de `public_html/tournoi-slots/assets/crown-film-v4.mp4`.

Les deux sorties sont des MP4 1280×720, 24 fps, 34 secondes, avec audio AAC intégré et faststart. L’application précharge les deux versions et sélectionne le propriétaire de la slot championne.
