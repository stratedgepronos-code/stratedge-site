-- =============================================================================
-- Migration v1.4 — Tables joueurs pour scoring buteur
-- A executer apres v1.3, via phpMyAdmin onglet SQL
-- =============================================================================
-- Permet de stocker :
-- 1. players : referentiel joueurs (api_id, nom, position, age, photo)
-- 2. player_stats_season : stats agregees + breakdown H/E
-- 3. player_vs_team : buts contre un adversaire specifique (carriere)
-- =============================================================================

-- Table referentiel joueurs
CREATE TABLE IF NOT EXISTS players (
    api_id          INT UNSIGNED PRIMARY KEY,           -- ID API-Football
    name            VARCHAR(100) NOT NULL,
    firstname       VARCHAR(60) NULL,
    lastname        VARCHAR(60) NULL,
    birth_date      DATE NULL,
    age             TINYINT UNSIGNED NULL,
    nationality     VARCHAR(60) NULL,
    height_cm       SMALLINT UNSIGNED NULL,
    weight_kg       SMALLINT UNSIGNED NULL,
    photo_url       VARCHAR(255) NULL,
    position_main   VARCHAR(20) NULL,                   -- Attacker / Midfielder / Defender / Goalkeeper
    -- meta
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_position (position_main)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table stats saison agregees (1 ligne par joueur/saison/equipe)
CREATE TABLE IF NOT EXISTS player_stats_season (
    player_id       INT UNSIGNED NOT NULL,
    season          SMALLINT UNSIGNED NOT NULL,         -- ex 2025 pour 2025-2026
    team_id         INT UNSIGNED NOT NULL,              -- API-Football team_id
    team_name       VARCHAR(100) NULL,
    -- Stats agregees toutes competitions
    appearances     SMALLINT UNSIGNED DEFAULT 0,
    lineups         SMALLINT UNSIGNED DEFAULT 0,
    minutes         SMALLINT UNSIGNED DEFAULT 0,
    rating          DECIMAL(3,2) NULL,
    goals           SMALLINT UNSIGNED DEFAULT 0,
    assists         SMALLINT UNSIGNED DEFAULT 0,
    shots_total     SMALLINT UNSIGNED DEFAULT 0,
    shots_on        SMALLINT UNSIGNED DEFAULT 0,
    passes_total    SMALLINT UNSIGNED DEFAULT 0,
    passes_key      SMALLINT UNSIGNED DEFAULT 0,
    passes_accuracy_pct TINYINT UNSIGNED DEFAULT 0,
    tackles_total   SMALLINT UNSIGNED DEFAULT 0,
    duels_total     SMALLINT UNSIGNED DEFAULT 0,
    duels_won       SMALLINT UNSIGNED DEFAULT 0,
    dribbles_attempts SMALLINT UNSIGNED DEFAULT 0,
    dribbles_success SMALLINT UNSIGNED DEFAULT 0,
    fouls_drawn     SMALLINT UNSIGNED DEFAULT 0,
    fouls_committed SMALLINT UNSIGNED DEFAULT 0,
    cards_yellow    SMALLINT UNSIGNED DEFAULT 0,
    cards_red       SMALLINT UNSIGNED DEFAULT 0,
    penalty_won     SMALLINT UNSIGNED DEFAULT 0,
    penalty_committed SMALLINT UNSIGNED DEFAULT 0,
    penalty_scored  SMALLINT UNSIGNED DEFAULT 0,
    penalty_missed  SMALLINT UNSIGNED DEFAULT 0,
    -- Breakdown H/E calcule via /fixtures + /fixtures/players
    matches_home    SMALLINT UNSIGNED DEFAULT 0,
    matches_away    SMALLINT UNSIGNED DEFAULT 0,
    minutes_home    SMALLINT UNSIGNED DEFAULT 0,
    minutes_away    SMALLINT UNSIGNED DEFAULT 0,
    goals_home      SMALLINT UNSIGNED DEFAULT 0,
    goals_away      SMALLINT UNSIGNED DEFAULT 0,
    -- meta
    last_refreshed  DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (player_id, season, team_id),
    FOREIGN KEY (player_id) REFERENCES players(api_id) ON DELETE CASCADE,
    INDEX idx_team_season (team_id, season),
    INDEX idx_goals (season, goals DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table buts vs adversaire (carriere : plusieurs saisons agregees)
CREATE TABLE IF NOT EXISTS player_vs_team (
    player_id       INT UNSIGNED NOT NULL,
    opponent_team_id INT UNSIGNED NOT NULL,
    seasons_scanned VARCHAR(50) NOT NULL,               -- ex "2023,2024,2025"
    matches_vs      SMALLINT UNSIGNED DEFAULT 0,
    goals_vs        SMALLINT UNSIGNED DEFAULT 0,
    assists_vs      SMALLINT UNSIGNED DEFAULT 0,
    minutes_vs      SMALLINT UNSIGNED DEFAULT 0,
    last_refreshed  DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (player_id, opponent_team_id),
    FOREIGN KEY (player_id) REFERENCES players(api_id) ON DELETE CASCADE,
    INDEX idx_opponent (opponent_team_id, goals_vs DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table de scoring buteur (calcule pour chaque match a venir)
-- Re-calcule a chaque import, donc on stocke tel quel sans foreign key match
CREATE TABLE IF NOT EXISTS player_scoring (
    match_id        BIGINT UNSIGNED NOT NULL,
    player_id       INT UNSIGNED NOT NULL,
    team_id         INT UNSIGNED NOT NULL,              -- equipe du joueur dans CE match
    is_home         TINYINT(1) NOT NULL,                -- 1 si joueur joue a domicile
    -- Score 0-100
    score_total     TINYINT UNSIGNED NOT NULL,
    score_quality   TINYINT UNSIGNED NOT NULL,
    score_volume    TINYINT UNSIGNED NOT NULL,
    score_matchup   TINYINT UNSIGNED NOT NULL,
    score_context   TINYINT UNSIGNED NOT NULL,
    score_history   TINYINT UNSIGNED NOT NULL,
    -- Snapshots des stats utilisees (pour debug + affichage)
    stats_snapshot  LONGTEXT NULL,                      -- JSON avec buts/H/E/H2H/etc
    -- meta
    computed_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (match_id, player_id),
    FOREIGN KEY (player_id) REFERENCES players(api_id) ON DELETE CASCADE,
    INDEX idx_match_score (match_id, score_total DESC),
    INDEX idx_team_match (match_id, team_id, score_total DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
