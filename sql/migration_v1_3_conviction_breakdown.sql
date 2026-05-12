-- =============================================================================
-- Migration v1.3 — Conviction tier-aware avec breakdown
-- A executer via phpMyAdmin onglet SQL
--
-- Cette migration :
--   1. Ajoute conv_flags si pas deja la
--   2. Ajoute conv_breakdown, conv_tier, conv_auto_eligible
-- =============================================================================

-- Etape 1 (DEBUG) : verifier d'abord ce qui existe deja
-- Lance ceci seul pour debug :
-- SHOW COLUMNS FROM pick_candidates LIKE 'conv%';

-- Etape 2 : ajout des colonnes (utilise ADD COLUMN IF NOT EXISTS pour eviter erreurs)
ALTER TABLE pick_candidates
  ADD COLUMN IF NOT EXISTS conv_flags        LONGTEXT NULL,
  ADD COLUMN IF NOT EXISTS conv_breakdown    LONGTEXT NULL,
  ADD COLUMN IF NOT EXISTS conv_tier         VARCHAR(10) NULL,
  ADD COLUMN IF NOT EXISTS conv_auto_eligible TINYINT(1) NOT NULL DEFAULT 0;

-- Note : MySQL 8.0+ supporte ADD COLUMN IF NOT EXISTS. Si ta version est plus
-- ancienne, lance les ADD COLUMN un par un en gerant les erreurs "Duplicate column".
