-- ============================================================
-- STRATEDGE — Migration production
-- Exécuter dans phpMyAdmin (onglet SQL) sur Hostinger
-- Chaque instruction utilise IF NOT EXISTS / IF pour éviter
-- les erreurs si déjà appliqué
-- ============================================================

-- ── 1. Table MEMBRES : colonnes manquantes ──────────────────

-- accepte_emails (consentement notification)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'membres' AND COLUMN_NAME = 'accepte_emails');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE membres ADD COLUMN accepte_emails TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- role
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'membres' AND COLUMN_NAME = 'role');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE membres ADD COLUMN role VARCHAR(20) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- photo_profil
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'membres' AND COLUMN_NAME = 'photo_profil');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE membres ADD COLUMN photo_profil VARCHAR(255) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- date_naissance
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'membres' AND COLUMN_NAME = 'date_naissance');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE membres ADD COLUMN date_naissance DATE DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ── 2. Table BETS : colonnes manquantes ─────────────────────

-- resultat
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bets' AND COLUMN_NAME = 'resultat');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE bets ADD COLUMN resultat ENUM(''en_cours'',''gagne'',''perdu'',''annule'') NOT NULL DEFAULT ''en_cours'' AFTER actif', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- date_resultat
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bets' AND COLUMN_NAME = 'date_resultat');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE bets ADD COLUMN date_resultat DATETIME NULL AFTER resultat', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- locked_image_path
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bets' AND COLUMN_NAME = 'locked_image_path');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE bets ADD COLUMN locked_image_path VARCHAR(255) DEFAULT NULL AFTER image_path', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- categorie
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bets' AND COLUMN_NAME = 'categorie');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE bets ADD COLUMN categorie VARCHAR(30) NOT NULL DEFAULT ''multi'' AFTER type', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sport (tennis, football, basket, hockey) pour historique par section
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bets' AND COLUMN_NAME = 'sport');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE bets ADD COLUMN sport VARCHAR(30) DEFAULT NULL AFTER categorie', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill sport pour bets existants (optionnel)
UPDATE bets SET sport = 'tennis' WHERE categorie = 'tennis' AND (sport IS NULL OR sport = '');
UPDATE bets SET sport = 'football' WHERE (sport IS NULL OR sport = '');


-- ── 3. Table ABONNEMENTS : convertir ENUM → VARCHAR ─────────
-- Pour supporter tennis, rasstoss et futures formules

ALTER TABLE abonnements MODIFY COLUMN `type` VARCHAR(30) NOT NULL DEFAULT 'daily';


-- ── 4. Table BETS : sauvegarde images en base (anti-suppression deploy) ──
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bets' AND COLUMN_NAME = 'image_data');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE bets ADD COLUMN image_data MEDIUMBLOB DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bets' AND COLUMN_NAME = 'locked_image_data');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE bets ADD COLUMN locked_image_data MEDIUMBLOB DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ── 5. Table VISITES : visiteurs uniques (visitor_id) ───────
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'visites' AND COLUMN_NAME = 'visitor_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE visites ADD COLUMN visitor_id VARCHAR(64) DEFAULT NULL COMMENT ''Hash IP+UA'' AFTER id, ADD KEY idx_visitor_t (visitor_id, t)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ── 5. Table PUSH_SUBSCRIPTIONS (si pas encore créée) ───────

CREATE TABLE IF NOT EXISTS `push_subscriptions` (
  `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `membre_id`  INT(11) UNSIGNED NOT NULL,
  `endpoint`   TEXT NOT NULL,
  `p256dh`     VARCHAR(255) NOT NULL,
  `auth`       VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_membre` (`membre_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ── 6. Tables CODES PROMO + anniversaire ─────────────────────
CREATE TABLE IF NOT EXISTS `codes_promo` (
  `id`                INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`              VARCHAR(50) NOT NULL,
  `type`              ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  `value`             DECIMAL(10,2) NOT NULL,
  `offres`            VARCHAR(200) NOT NULL DEFAULT '',
  `max_utilisations`  INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `utilisations`      INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `date_expir`        DATE DEFAULT NULL,
  `actif`             TINYINT(1) NOT NULL DEFAULT 1,
  `date_creation`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_actif` (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `code_promo_utilisations` (
  `id`            INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code_promo_id` INT(11) UNSIGNED NOT NULL,
  `membre_id`     INT(11) UNSIGNED NOT NULL,
  `offre`         VARCHAR(30) NOT NULL,
  `montant_avant` DECIMAL(10,2) NOT NULL,
  `montant_apres` DECIMAL(10,2) NOT NULL,
  `date_utilisation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_code` (`code_promo_id`),
  KEY `idx_membre` (`membre_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `promo_anniversaire_use` (
  `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `membre_id`  INT(11) UNSIGNED NOT NULL,
  `annee`      SMALLINT UNSIGNED NOT NULL,
  `offre`      VARCHAR(30) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `membre_annee` (`membre_id`,`annee`),
  KEY `idx_membre` (`membre_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ── 7. BETS : analyse HTML + cote (page bet membre + cote moyenne) ──
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bets' AND COLUMN_NAME = 'analyse_html');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE bets ADD COLUMN analyse_html MEDIUMTEXT NULL DEFAULT NULL AFTER description', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bets' AND COLUMN_NAME = 'cote');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE bets ADD COLUMN cote DECIMAL(10,2) NULL DEFAULT NULL COMMENT ''Cote du pari (pour cote moyenne)'' AFTER analyse_html', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 8. Table BET_COMMENTS (commentaires sous chaque bet) ─────
CREATE TABLE IF NOT EXISTS `bet_comments` (
  `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `bet_id`     INT(11) UNSIGNED NOT NULL,
  `membre_id`  INT(11) UNSIGNED NOT NULL,
  `contenu`    TEXT NOT NULL,
  `date_post`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bet` (`bet_id`),
  KEY `idx_membre` (`membre_id`),
  FOREIGN KEY (`bet_id`) REFERENCES `bets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`membre_id`) REFERENCES `membres`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ── 9. Prono de la commu : matchs à voter + votes ───────────
CREATE TABLE IF NOT EXISTS `commu_matches` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `match_date`    DATE NOT NULL COMMENT 'Date du match',
  `team_home`     VARCHAR(120) NOT NULL,
  `team_away`     VARCHAR(120) NOT NULL,
  `competition`   VARCHAR(120) DEFAULT NULL,
  `heure`         VARCHAR(20) DEFAULT NULL COMMENT 'Ex: 21:00',
  `vote_closed_at` DATETIME NOT NULL COMMENT 'Fin des votes (ex: veille 23:59)',
  `is_winner`     TINYINT(1) NOT NULL DEFAULT 0,
  `analysis_html` MEDIUMTEXT DEFAULT NULL COMMENT 'Analyse postée par admin',
  `analysis_at`   DATETIME DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_match_date` (`match_date`),
  KEY `idx_vote_closed` (`vote_closed_at`),
  KEY `idx_winner` (`is_winner`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `commu_votes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `match_id`   INT UNSIGNED NOT NULL,
  `membre_id`  INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `one_vote_per_member_per_round` (`membre_id`,`match_id`),
  KEY `idx_match` (`match_id`),
  KEY `idx_membre` (`membre_id`),
  FOREIGN KEY (`match_id`) REFERENCES `commu_matches`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`membre_id`) REFERENCES `membres`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ── 10. Montante Tennis ──────────────────────────────────────

CREATE TABLE IF NOT EXISTS `montante_config` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nom` VARCHAR(120) NOT NULL DEFAULT 'Montante Tennis',
  `bankroll_initial` DECIMAL(10,2) NOT NULL DEFAULT 100.00,
  `mise_depart` DECIMAL(10,2) NOT NULL DEFAULT 10.00,
  `statut` ENUM('active','pause','terminee') DEFAULT 'active',
  `date_debut` DATE DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `montante_steps` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `montante_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `step_number` INT UNSIGNED NOT NULL,
  `match_desc` VARCHAR(255) NOT NULL,
  `competition` VARCHAR(120) DEFAULT NULL,
  `cote` DECIMAL(10,2) NOT NULL,
  `mise` DECIMAL(10,2) NOT NULL,
  `resultat` ENUM('en_cours','gagne','perdu','annule') DEFAULT 'en_cours',
  `gain_perte` DECIMAL(10,2) DEFAULT NULL,
  `bankroll_apres` DECIMAL(10,2) DEFAULT NULL,
  `date_match` DATE DEFAULT NULL,
  `heure` VARCHAR(20) DEFAULT NULL,
  `analyse` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_montante` (`montante_id`),
  KEY `idx_step` (`step_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ── 11. Abonnement type 'fun' (élargir VARCHAR si pas déjà fait) ──
-- Le type 'fun' est un abonnement optionnel qui donne accès aux bets Fun
ALTER TABLE abonnements MODIFY COLUMN `type` VARCHAR(30) NOT NULL DEFAULT 'daily';


-- ── 12. Vérification ─────────────────────────────────────────
-- Après exécution, vérifier avec :
-- DESCRIBE bets;
-- DESCRIBE bet_comments;
-- DESCRIBE membres;
-- DESCRIBE codes_promo;
-- DESCRIBE montante_config;
-- DESCRIBE montante_steps;
