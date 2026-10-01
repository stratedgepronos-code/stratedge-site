-- =============================================================================
-- Migration v1.1 — Ajout stats FootyStats sur pick_matches
-- À exécuter UNE SEULE FOIS sur la DB Hostinger via phpMyAdmin onglet SQL
-- =============================================================================

ALTER TABLE pick_matches
  ADD COLUMN home_xg_prematch  DECIMAL(5,3) NULL AFTER lambda_total,
  ADD COLUMN away_xg_prematch  DECIMAL(5,3) NULL AFTER home_xg_prematch,
  ADD COLUMN home_ppg          DECIMAL(4,2) NULL AFTER away_xg_prematch,
  ADD COLUMN away_ppg          DECIMAL(4,2) NULL AFTER home_ppg,
  ADD COLUMN btts_potential    DECIMAL(5,2) NULL AFTER away_ppg,
  ADD COLUMN o25_potential     DECIMAL(5,2) NULL AFTER btts_potential,
  ADD COLUMN o35_potential     DECIMAL(5,2) NULL AFTER o25_potential,
  ADD COLUMN avg_potential     DECIMAL(5,2) NULL AFTER o35_potential,
  ADD COLUMN btts_fhg_potential DECIMAL(5,2) NULL AFTER avg_potential,
  ADD COLUMN btts_2hg_potential DECIMAL(5,2) NULL AFTER btts_fhg_potential,
  ADD COLUMN corners_potential  DECIMAL(5,2) NULL AFTER btts_2hg_potential,
  ADD COLUMN corners_o85_potential  DECIMAL(5,2) NULL AFTER corners_potential,
  ADD COLUMN corners_o95_potential  DECIMAL(5,2) NULL AFTER corners_o85_potential,
  ADD COLUMN corners_o105_potential DECIMAL(5,2) NULL AFTER corners_o95_potential,
  ADD COLUMN cards_potential       DECIMAL(5,2) NULL AFTER corners_o105_potential,
  ADD COLUMN highlights JSON NULL AFTER cards_potential;
