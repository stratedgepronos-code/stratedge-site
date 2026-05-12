-- =============================================================================
-- Migration v1.2 — Stats Over HT/2H + team metrics derives DC
-- À executer apres v1.1, via phpMyAdmin onglet SQL
-- =============================================================================

ALTER TABLE pick_matches
  ADD COLUMN o05ht_potential   DECIMAL(5,2) NULL AFTER cards_potential,
  ADD COLUMN o15ht_potential   DECIMAL(5,2) NULL AFTER o05ht_potential,
  ADD COLUMN o05_2h_potential  DECIMAL(5,2) NULL AFTER o15ht_potential,
  ADD COLUMN o15_2h_potential  DECIMAL(5,2) NULL AFTER o05_2h_potential,
  ADD COLUMN o05_potential     DECIMAL(5,2) NULL AFTER o15_2h_potential,
  ADD COLUMN o15_potential     DECIMAL(5,2) NULL AFTER o05_potential,
  ADD COLUMN u05_potential     DECIMAL(5,2) NULL AFTER o15_potential,
  ADD COLUMN u15_potential     DECIMAL(5,2) NULL AFTER u05_potential,
  ADD COLUMN u25_potential     DECIMAL(5,2) NULL AFTER u15_potential,
  ADD COLUMN offsides_potential DECIMAL(5,2) NULL AFTER u25_potential,
  ADD COLUMN team_metrics      JSON NULL AFTER highlights;
