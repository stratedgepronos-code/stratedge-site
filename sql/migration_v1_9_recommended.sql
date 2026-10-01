-- =============================================================================
-- Migration v1.9 — Pick recommande (anti-correlation)
-- =============================================================================
-- Brief Edge Finder v2, point P1.1 : anti-correlation.
-- Pour eviter de parier plusieurs marches correles d'un meme match, le
-- pipeline (export_picks.py) marque desormais UN seul candidat par match
-- comme 'recommande' (regle : auto > manual, puis meilleur EV).
--
-- Cette colonne stocke ce flag cote dashboard, pour mettre le pick en avant.
--
-- A appliquer sur Hostinger via phpMyAdmin (base u527192911_u527192911_edg).
-- =============================================================================

ALTER TABLE pick_candidates
    ADD COLUMN recommended TINYINT(1) NOT NULL DEFAULT 0 AFTER conv_auto_eligible;
