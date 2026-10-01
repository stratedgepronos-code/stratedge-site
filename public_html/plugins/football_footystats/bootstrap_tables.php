<?php
/**
 * Tables cache FootyStats (préfixe fd_fy_).
 * Usage : php bootstrap_tables.php
 */
declare(strict_types=1);

$pdo = require __DIR__ . '/inc/bootstrap_pdo.php';

$sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS fd_fy_sync_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME DEFAULT NULL,
    action VARCHAR(128) NOT NULL,
    status ENUM('running','ok','error') NOT NULL DEFAULT 'running',
    message TEXT,
    meta JSON DEFAULT NULL,
    INDEX idx_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fd_fy_league (
    fy_league_id INT NOT NULL PRIMARY KEY,
    name VARCHAR(255) DEFAULT NULL,
    country VARCHAR(128) DEFAULT NULL,
    season VARCHAR(64) DEFAULT NULL,
    raw_json JSON DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fd_fy_team (
    fy_team_id INT NOT NULL PRIMARY KEY,
    name VARCHAR(255) DEFAULT NULL,
    raw_json JSON DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fd_fy_match (
    fy_match_id BIGINT NOT NULL PRIMARY KEY,
    date_unix INT UNSIGNED DEFAULT NULL,
    match_datetime DATETIME DEFAULT NULL,
    home_team_fy_id INT DEFAULT NULL,
    away_team_fy_id INT DEFAULT NULL,
    home_name VARCHAR(255) DEFAULT NULL,
    away_name VARCHAR(255) DEFAULT NULL,
    status VARCHAR(64) DEFAULT NULL,
    competition_id INT DEFAULT NULL,
    league_name VARCHAR(255) DEFAULT NULL,
    country VARCHAR(128) DEFAULT NULL,
    home_goals TINYINT DEFAULT NULL,
    away_goals TINYINT DEFAULT NULL,
    list_json JSON DEFAULT NULL,
    detail_json JSON DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_date_unix (date_unix),
    INDEX idx_home (home_team_fy_id),
    INDEX idx_away (away_team_fy_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    if ($stmt === '' || strpos($stmt, '--') === 0) {
        continue;
    }
    $pdo->exec($stmt);
}

echo "OK — tables fd_fy_* créées ou déjà présentes.\n";
