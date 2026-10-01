-- =============================================================================
-- Migration v1.3 — Colonnes pour conviction tier-aware
-- À executer apres v1.2, via phpMyAdmin onglet SQL
-- =============================================================================
-- Ajoute 4 colonnes a pick_candidates pour stocker les details du scoring
-- tier-aware (tier_used, breakdown, flags, eligibilite auto).

ALTER TABLE pick_candidates
  ADD COLUMN conv_flags          LONGTEXT NULL AFTER below_min_odds,
  ADD COLUMN conv_breakdown      LONGTEXT NULL AFTER conv_flags,
  ADD COLUMN conv_tier           VARCHAR(10) NULL AFTER conv_breakdown,
  ADD COLUMN conv_auto_eligible  TINYINT(1) NOT NULL DEFAULT 0 AFTER conv_tier;

-- Index pour filtrer rapidement les auto-eligibles
ALTER TABLE pick_candidates
  ADD INDEX idx_auto_eligible (conv_auto_eligible, conviction DESC);
