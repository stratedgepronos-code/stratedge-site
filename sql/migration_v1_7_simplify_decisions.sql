-- =============================================================================
-- Migration v1.7 - Simplification systeme de decision
-- =============================================================================
-- AVANT : pending / validated / rejected / published / won / lost / void
-- APRES : pending / tracked / skipped / won / lost / void
--
-- Mapping :
--   validated  -> tracked   (je suis ce pick)
--   published  -> tracked   (fusionne avec validated)
--   rejected   -> skipped   (je passe)
--   pending/won/lost/void   -> inchanges
--
-- A executer via phpMyAdmin onglet SQL.
-- =============================================================================

-- Etape 1 : elargir l'enum pour accueillir les nouvelles valeurs en plus des anciennes
ALTER TABLE pick_candidates
  MODIFY COLUMN user_decision
  ENUM('pending','validated','rejected','published','won','lost','void','tracked','skipped')
  NOT NULL DEFAULT 'pending';

-- Etape 2 : migrer les donnees existantes
UPDATE pick_candidates SET user_decision = 'tracked' WHERE user_decision = 'validated';
UPDATE pick_candidates SET user_decision = 'tracked' WHERE user_decision = 'published';
UPDATE pick_candidates SET user_decision = 'skipped' WHERE user_decision = 'rejected';

-- Etape 3 : reduire l'enum a sa forme finale (les anciennes valeurs ne sont plus utilisees)
ALTER TABLE pick_candidates
  MODIFY COLUMN user_decision
  ENUM('pending','tracked','skipped','won','lost','void')
  NOT NULL DEFAULT 'pending';
