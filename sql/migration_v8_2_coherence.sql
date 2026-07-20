-- ============================================================
-- StratEdge Edge Finder — migration v8.2 « filtres de cohérence »
-- Persiste les verdicts du module src/filters/coherence.py pour
-- pouvoir répondre en UNE requête à : « pourquoi ce pick n'est
-- pas sorti ? » (contrainte transverse #2 du brief).
-- Idempotent : relançable sans risque (IF NOT EXISTS).
-- ============================================================

-- Niveau CANDIDAT ------------------------------------------------------
ALTER TABLE pick_candidates
  ADD COLUMN IF NOT EXISTS recommendable TINYINT(1) NOT NULL DEFAULT 1
    COMMENT 'v8.2 : survit aux filtres P1 (0 = rejete)',
  ADD COLUMN IF NOT EXISTS tracking_only TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'v8.2 P1.1 : Under suivi pour mesure, jamais recommande',
  ADD COLUMN IF NOT EXISTS rejections JSON NULL
    COMMENT 'v8.2 : [{market, reason}] — raisons de rejet pour audit';

-- Niveau MATCH ---------------------------------------------------------
ALTER TABLE pick_matches
  ADD COLUMN IF NOT EXISTS data_suspect TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'v8.2 P1.3 : potentials corrompus -> aucun pick sur le match',
  ADD COLUMN IF NOT EXISTS quarantine TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'v8.2 P1.4 : desaccord DC vs potentials -> pas d auto',
  ADD COLUMN IF NOT EXISTS coherence_json JSON NULL
    COMMENT 'v8.2 : raisons match + best_signal_missed';

-- Index d audit : retrouver vite les rejets du jour
ALTER TABLE pick_candidates
  ADD INDEX IF NOT EXISTS idx_recommendable (recommendable);
