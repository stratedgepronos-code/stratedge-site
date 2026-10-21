# StratEdge Live — 25 cases, collecteur 5.1.0

Mise à jour du 21 octobre 2026. Dans **Statistiques en direct**, conserver ton groupe actuel. Les cases affichent des colonnes ; elles ne doivent pas éliminer des matchs. Cette liste reprend les 19 cases de ton PDF et ajoute 6 cases, pour un total de **25 sur 40**.

| Menu | Cases à cocher | Nombre |
|---|---|---:|
| Statistiques Temps plein | Buts ; Corners ; Total des tirs ; Tirs cadrés ; Tirs hors cible ; Tirs bloqués ; Photos à l’intérieur de la boîte ; Tirs hors boîte ; Possession ; Cartons jaunes ; Cartons rouges ; Cartons jaunes-rouges ; Substitutions ; Fautes ; Tacles ; Attaques dangereuses | 16 |
| Statistiques 10 dernières minutes | Corners ; Total des tirs ; Tirs cadrés ; Attaques dangereuses | 4 |
| Statistiques 5 dernières minutes | Buts ; Corners ; Total des tirs ; Tirs cadrés ; Attaques dangereuses | 5 |

## Les six ajouts à ton groupe actuel

- Statistiques Temps plein → Tirs hors cible.
- Statistiques Temps plein → Tirs hors boîte.
- Statistiques Temps plein → Tacles.
- Statistiques Temps plein → Attaques dangereuses (compteur, pas AVG/par minute).
- Statistiques 10 dernières minutes → Attaques dangereuses.
- Statistiques 5 dernières minutes → Attaques dangereuses.

Les tirs dans la surface, tirs bloqués et substitutions sont déjà cochés dans ton PDF. Les corners restent seulement un contexte collecté : aucun marché corners n'est réactivé. Ne modifie pas tes deux exports avant-match GPT / GPT - 2.

## Deux cases facultatives pour observation

Indicateurs 10 dernières minutes → Buts attendus (ExG 10′).
Indicateurs 5 dernières minutes → Buts attendus (ExG 5′).

Avec ces deux cases : **27 sur 40**. Aucun ExG propriétaire ne pilote les alertes ni ne remplace un xG de tirs sans documentation du calcul. Ne pas ajouter des indices de pression pour prétendre multiplier des preuves indépendantes. Tu peux conserver des colonnes de cotes dans la limite totale de 40.

## Installer le collecteur 5.1.0

StratEdge → Réglages → Copier le code ou Télécharger le fichier. Dans Tampermonkey, ouvrir le script existant **SEUIL90 · Packball Live Console**, remplacer son code, Ctrl+S, puis recharger Packball. Le nom, le namespace et la clé de stockage SE90_TOKEN restent identiques : ne pas supprimer/recréer le script. Garder un seul collecteur actif.

Le badge affiche **S90 v5.1.0** puis le nombre de relevés reçus. Le collecteur lit les libellés, pas une position fixe ; les données nouvelles sont facultatives. Il distingue maintenant les attaques dangereuses cumulées et leur moyenne par minute, les tirs hors cible/hors surface et les tacles. Les cellules vides restent inconnues.

Laisser Packball ouvert sur le tableau live. Les lignes hors écran sont lues uniquement si présentes dans le DOM. Un match virtualisé/non chargé n'est pas accessible. Garder une collecte continue : les différences de compteurs demandent des relevés datant de 5 et 10 minutes ; après un but, une interruption ou une pause, les fenêtres doivent se reconstruire. Une réception périmée ne déclenche rien.

## Ce que fait le moteur

- Buts : repères FT par localisation et contrôle tirs/cadrés conservés ; qualité partielle, score, minutes restantes et évolution 5 min versus 5 min précédentes explicités. Aucun pourcentage inventé.
- Cartons : références arbitre et équipes maintenues. Deux comparaisons arbitre trop rigides deviennent des réserves, sans veto automatique. La possession reste celle du match entier ; les fautes par joueur et leur gravité ne sont pas disponibles dans ce filtre.
- Une nouvelle alerte suit un événement supplémentaire FT. Un cut qui en demande plusieurs est signalé comme marché distinct ; les anciennes cibles de résultats sont conservées.
- Les nouveaux seuils de qualité des tirs sont enregistrés en comparaison interne, avec les non-signaux, dans l'export des observations. Ils ne pilotent pas les notifications avant validation hors échantillon.
- Aucun abonnement, nouvel appel IA ou appel automatique de cotes n'est ajouté.
