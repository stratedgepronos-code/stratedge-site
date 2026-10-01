-- =============================================================================
-- StratEdge Edge Finder — Schema MySQL Hostinger v1.0
--
-- À exécuter UNE SEULE FOIS sur la base de données MySQL Hostinger.
-- Lance via phpMyAdmin → onglet SQL → coller ce contenu → Exécuter.
-- =============================================================================

-- Table 1 : registre des imports JSON quotidiens
CREATE TABLE IF NOT EXISTS picks_imports (
    import_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    generated_at      DATETIME NOT NULL,
    imported_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    version           VARCHAR(10),
    horizon_days      TINYINT UNSIGNED,
    matchs_total      SMALLINT UNSIGNED,
    matchs_analyses   SMALLINT UNSIGNED,
    candidates_auto   SMALLINT UNSIGNED,
    candidates_manual SMALLINT UNSIGNED,
    candidates_warn   SMALLINT UNSIGNED,
    scope_json        JSON,
    INDEX idx_imported (imported_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table 2 : matchs analysés (1 ligne par match)
CREATE TABLE IF NOT EXISTS pick_matches (
    match_id         BIGINT UNSIGNED PRIMARY KEY,
    import_id        INT UNSIGNED NOT NULL,
    season_id        INT UNSIGNED,
    league_name      VARCHAR(100),
    league_tier      VARCHAR(10),
    league_country   VARCHAR(50),
    kickoff_utc      DATETIME NOT NULL,
    home_id          INT UNSIGNED,
    home_name        VARCHAR(100),
    away_id          INT UNSIGNED,
    away_name        VARCHAR(100),
    lambda_home      DECIMAL(6,3),
    lambda_away      DECIMAL(6,3),
    lambda_total     DECIMAL(6,3),
    n_auto           TINYINT UNSIGNED,
    n_manual         TINYINT UNSIGNED,
    n_warn           TINYINT UNSIGNED,
    best_conviction  TINYINT UNSIGNED,
    INDEX idx_kickoff (kickoff_utc),
    INDEX idx_league (league_name, league_tier),
    INDEX idx_import (import_id),
    FOREIGN KEY (import_id) REFERENCES picks_imports(import_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table 3 : candidats (1 ligne par marché analysé)
CREATE TABLE IF NOT EXISTS pick_candidates (
    candidate_id    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    match_id        BIGINT UNSIGNED NOT NULL,
    market          VARCHAR(40) NOT NULL,
    market_group    ENUM('FT','HT','2H') NOT NULL,
    model_proba     DECIMAL(6,4),
    devig_proba     DECIMAL(6,4),
    odds            DECIMAL(6,2),
    ev              DECIMAL(7,4),
    status          ENUM('auto','manual','warn','neutral') NOT NULL,
    conviction      TINYINT UNSIGNED NOT NULL,
    below_min_odds  BOOLEAN DEFAULT FALSE,
    user_decision   ENUM('pending','validated','rejected','published','won','lost','void') DEFAULT 'pending',
    decision_at     DATETIME NULL,
    decision_note   TEXT NULL,
    INDEX idx_match (match_id),
    INDEX idx_status (status, conviction DESC),
    INDEX idx_decision (user_decision),
    FOREIGN KEY (match_id) REFERENCES pick_matches(match_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
