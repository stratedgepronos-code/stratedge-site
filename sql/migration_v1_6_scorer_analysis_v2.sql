-- =============================================================================
-- Migration v1.6 - Etendre match_scorer_analysis pour 3 buteurs + radar + markdown
-- A executer apres v1.5 via phpMyAdmin
-- =============================================================================

-- Drop l'ancienne table (creee en v1.5) et la recreer avec la nouvelle structure
DROP TABLE IF EXISTS match_scorer_analysis;

CREATE TABLE match_scorer_analysis (
    match_id        INT UNSIGNED PRIMARY KEY,
    -- Top 3 buteurs (JSON arrays pour rester flexibles)
    scorers_json    LONGTEXT NOT NULL,        -- [{name, team, photo, sniper_score, stars, odds, ev, radar:{...}, reasoning, breakdown:{...}}, ...]
    -- Markdown complet (rendu Claude format cyberpunk pour bouton 'Voir analyse complete')
    markdown_full   LONGTEXT NULL,
    -- Warnings + notes
    warnings_json   LONGTEXT NULL,            -- [{level: 'critical'|'warning'|'info', text: '...'}, ...]
    freshness_note  VARCHAR(500) NULL,
    -- Searches faites pendant l'analyse (pour stream replay si rechargement cache)
    searches_json   LONGTEXT NULL,            -- [{query, timestamp}, ...]
    -- Meta technique
    raw_response    LONGTEXT NULL,
    model_used      VARCHAR(50) NOT NULL DEFAULT 'claude-opus-4-7',
    tokens_input    SMALLINT UNSIGNED NULL,
    tokens_output   MEDIUMINT UNSIGNED NULL,
    web_searches_count TINYINT UNSIGNED DEFAULT 0,
    cost_usd        DECIMAL(7,4) NULL,
    duration_seconds SMALLINT UNSIGNED NULL,
    generated_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_generated (generated_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
