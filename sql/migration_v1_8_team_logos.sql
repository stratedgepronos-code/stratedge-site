-- =============================================================================
-- Migration v1.8 — Logos d'equipes
-- =============================================================================
-- Ajoute 2 colonnes a pick_matches pour stocker l'URL du logo de chaque equipe.
-- Source : FootyStats fournit teams.image_url (relatif, ex "teams/xxx.png").
-- Le pipeline (export_picks.py) joint teams.image_url et l'envoie dans le JSON.
-- L'affichage PHP prefixe avec https://cdn.footystats.org/img/ si besoin.
--
-- A appliquer sur Hostinger via phpMyAdmin (base u527192911_u527192911_edg).
-- =============================================================================

ALTER TABLE pick_matches
    ADD COLUMN home_logo VARCHAR(255) NULL AFTER away_name,
    ADD COLUMN away_logo VARCHAR(255) NULL AFTER home_logo;
