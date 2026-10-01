-- NOTE : MySQL de prod ne supporte pas ADD COLUMN IF NOT EXISTS (MariaDB only).
-- Utiliser de preference admin/edge-finder/api/migrate_v8_2.php (idempotent).
-- ============================================================
-- StratEdge Edge Finder — migration v8.2 « filtres de cohérence »
-- Persiste les verdicts du module src/filters/coherence.py pour
-- pouvoir répondre en UNE requête à : « pourquoi ce pick n'est
-- pas sorti ? » (contrainte transverse #2 du brief).
-- Idempotent : relançable sans risque (IF NOT EXISTS).
-- ============================================================

-- Niveau CANDIDAT ------------------------------------------------------
ALTER TABLE pick_candidates
  ADD COLUMN recommendable TINYINT(1) NOT NULL DEFAULT 1
    COMMENT 'v8.2 : survit aux filtres P1 (0 = rejete)',
  ADD COLUMN tracking_only TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'v8.2 P1.1 : Under suivi pour mesure, jamais recommande',
  ADD COLUMN rejections JSON NULL
    COMMENT 'v8.2 : [{market, reason}] — raisons de rejet pour audit';

-- Niveau MATCH ---------------------------------------------------------
ALTER TABLE pick_matches
  ADD COLUMN data_suspect TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'v8.2 P1.3 : potentials corrompus -> aucun pick sur le match',
  ADD COLUMN quarantine TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'v8.2 P1.4 : desaccord DC vs potentials -> pas d auto',
  ADD COLUMN coherence_json JSON NULL
    COMMENT 'v8.2 : raisons match + best_signal_missed';

-- Index d audit : retrouver vite les rejets du jour
ALTER TABLE pick_candidates
  ADD INDEX idx_recommendable (recommendable);
