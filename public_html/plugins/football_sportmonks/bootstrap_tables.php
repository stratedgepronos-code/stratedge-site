<?php
/**
 * Crée les tables du plugin football SportMonks (préfixe fd_ = football data).
 * Usage : php bootstrap_tables.php
 * StratEdge : BDD via includes/db.php. Sinon variables FD_DB_* ou local_config.php.
 */
declare(strict_types=1);

$pdo = require __DIR__ . '/inc/bootstrap_pdo.php';

$sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS fd_sm_sync_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME DEFAULT NULL,
    action VARCHAR(64) NOT NULL,
    status ENUM('running','ok','error') NOT NULL DEFAULT 'running',
    message TEXT,
    meta JSON DEFAULT NULL,
    INDEX idx_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fd_sm_league (
    sm_id INT NOT NULL PRIMARY KEY,
    name VARCHAR(255) DEFAULT NULL,
    country_id INT DEFAULT NULL,
    raw_json JSON DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fd_sm_team (
    sm_id BIGINT NOT NULL PRIMARY KEY,
    name VARCHAR(255) DEFAULT NULL,
    short_code VARCHAR(32) DEFAULT NULL,
    country_id INT DEFAULT NULL,
    national_team TINYINT(1) DEFAULT NULL,
    image_path VARCHAR(512) DEFAULT NULL,
    raw_json JSON DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_country (country_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fd_sm_venue (
    sm_id INT NOT NULL PRIMARY KEY,
    name VARCHAR(255) DEFAULT NULL,
    city_name VARCHAR(128) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    capacity INT DEFAULT NULL,
    surface VARCHAR(64) DEFAULT NULL,
    raw_json JSON DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fd_sm_referee (
    sm_id INT NOT NULL PRIMARY KEY,
    name VARCHAR(255) DEFAULT NULL,
    raw_json JSON DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fd_sm_fixture (
    sm_id BIGINT NOT NULL PRIMARY KEY,
    league_sm_id INT DEFAULT NULL,
    season_sm_id INT DEFAULT NULL,
    venue_sm_id INT DEFAULT NULL,
    state_id INT DEFAULT NULL,
    starting_at DATETIME DEFAULT NULL,
    name VARCHAR(512) DEFAULT NULL,
    result_info TEXT,
    home_team_sm_id BIGINT DEFAULT NULL,
    away_team_sm_id BIGINT DEFAULT NULL,
    referee_sm_id INT DEFAULT NULL,
    scores_json JSON DEFAULT NULL,
    summary_json JSON DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_starting (starting_at),
    INDEX idx_league (league_sm_id),
    INDEX idx_home (home_team_sm_id),
    INDEX idx_away (away_team_sm_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fd_sm_fixture_detail (
    fixture_sm_id BIGINT NOT NULL PRIMARY KEY,
    payload JSON NOT NULL,
    fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_fd_detail_fixture FOREIGN KEY (fixture_sm_id) REFERENCES fd_sm_fixture(sm_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    if ($stmt === '' || strpos($stmt, '--') === 0) {
        continue;
    }
    $pdo->exec($stmt);
}

echo "OK — tables fd_sm_* créées ou déjà présentes.\n";
