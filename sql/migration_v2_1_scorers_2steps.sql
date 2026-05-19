-- =============================================================================
-- Migration v2.1 — Worker buteurs en 2 etapes
-- =============================================================================
-- Le worker monolithique depasse 120s (limite serveur Hostinger). On le coupe
-- en 2 etapes Anthropic separees, chacune dans sa requete <120s :
--   Etape 1 (research) : recherches web -> synthese structuree (mini-JSON)
--   Etape 2 (writer)   : synthese -> JSON final SNIPER (cards buteurs)
--
-- A appliquer sur Hostinger via phpMyAdmin.
-- =============================================================================

-- Ajoute le statut intermediaire 'researched' (entre running et done).
-- Cycle complet : pending -> running -> researched -> running -> done
--                                                            -> error
ALTER TABLE match_scorer_analysis
    MODIFY COLUMN status ENUM('pending','running','researched','done','error') NOT NULL DEFAULT 'done';

-- Stocke la synthese intermediaire produite par l'etape 1 (mini-JSON brut).
-- Sert d'input a l'etape 2 ET de log pour debug.
ALTER TABLE match_scorer_analysis
    ADD COLUMN research_summary LONGTEXT NULL AFTER searches_json;

-- Compteurs separes par etape, pour le suivi des couts/temps.
ALTER TABLE match_scorer_analysis
    ADD COLUMN research_tokens_input MEDIUMINT UNSIGNED NULL AFTER research_summary,
    ADD COLUMN research_tokens_output MEDIUMINT UNSIGNED NULL AFTER research_tokens_input,
    ADD COLUMN research_duration_seconds SMALLINT UNSIGNED NULL AFTER research_tokens_output;
