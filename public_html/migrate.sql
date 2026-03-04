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


-- ── 3. Table ABONNEMENTS : convertir ENUM → VARCHAR ─────────
-- Pour supporter tennis, rasstoss et futures formules

ALTER TABLE abonnements MODIFY COLUMN `type` VARCHAR(30) NOT NULL DEFAULT 'daily';


-- ── 4. Table VISITES : visiteurs uniques (visitor_id) ───────
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


-- ── 6. Vérification ─────────────────────────────────────────
-- Après exécution, vérifier avec :
-- DESCRIBE bets;
-- DESCRIBE membres;
-- DESCRIBE abonnements;
-- SELECT * FROM push_subscriptions LIMIT 1;
