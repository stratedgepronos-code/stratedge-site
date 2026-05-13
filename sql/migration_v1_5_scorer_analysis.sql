-- =============================================================================
-- Migration v1.5 - Cache analyse buteurs probables (Claude Opus 4.7)
-- A executer via phpMyAdmin
-- =============================================================================

CREATE TABLE IF NOT EXISTS match_scorer_analysis (
    match_id        INT UNSIGNED PRIMARY KEY,
    scorer_1_name   VARCHAR(150) NOT NULL,
    scorer_1_team   VARCHAR(10)  NOT NULL,           -- 'home' ou 'away'
    scorer_1_conf   VARCHAR(10)  NOT NULL,           -- 'Forte' | 'Moyenne' | 'Faible'
    scorer_1_reason TEXT         NOT NULL,
    scorer_2_name   VARCHAR(150) NOT NULL,
    scorer_2_team   VARCHAR(10)  NOT NULL,
    scorer_2_conf   VARCHAR(10)  NOT NULL,
    scorer_2_reason TEXT         NOT NULL,
    warnings_json   LONGTEXT     NULL,               -- JSON array des warnings
    freshness_note  VARCHAR(255) NULL,
    raw_response    LONGTEXT     NULL,               -- pour debug
    model_used      VARCHAR(50)  NOT NULL DEFAULT 'claude-opus-4-7',
    tokens_input    SMALLINT UNSIGNED NULL,
    tokens_output   SMALLINT UNSIGNED NULL,
    cost_usd        DECIMAL(6,4) NULL,
    generated_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_generated (generated_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
