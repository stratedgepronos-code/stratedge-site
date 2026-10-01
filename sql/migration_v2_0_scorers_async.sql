-- =============================================================================
-- Migration v2.0 — Analyse buteurs asynchrone
-- =============================================================================
-- L'analyse buteurs (recherches web + redaction) depasse la limite serveur
-- Hostinger de ~120s. On passe en architecture asynchrone : un worker tourne
-- en tache de fond, le navigateur fait du polling.
--
-- Cette migration ajoute le suivi d'etat du job a match_scorer_analysis :
--   - status     : pending (cree) / running (worker demarre) / done / error
--   - error_msg  : message en cas d'echec
--   - started_at : debut du worker (pour detecter les jobs zombies)
--
-- A appliquer sur Hostinger via phpMyAdmin (base u527192911_u527192911_edg).
-- =============================================================================

ALTER TABLE match_scorer_analysis
    ADD COLUMN status ENUM('pending','running','done','error') NOT NULL DEFAULT 'done' AFTER match_id,
    ADD COLUMN error_msg VARCHAR(500) NULL AFTER status,
    ADD COLUMN started_at DATETIME NULL AFTER error_msg;

-- scorers_json doit pouvoir etre NULL : un job 'pending' n'a pas encore de resultat
ALTER TABLE match_scorer_analysis
    MODIFY COLUMN scorers_json LONGTEXT NULL;

-- Les analyses deja en cache sont evidemment terminees
UPDATE match_scorer_analysis SET status = 'done' WHERE scorers_json IS NOT NULL AND scorers_json != '';
