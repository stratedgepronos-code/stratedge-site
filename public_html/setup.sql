-- ============================================================
-- STRATEDGE PRONOS — Base de données complète
-- À importer dans phpMyAdmin sur Hostinger
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+01:00';

-- ── TABLE MEMBRES ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `membres` (
  `id`               INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`              VARCHAR(80) NOT NULL,
  `email`            VARCHAR(150) NOT NULL UNIQUE,
  `password`         VARCHAR(255) NOT NULL,
  `token_session`    VARCHAR(64) DEFAULT NULL,
  `date_inscription` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actif`            TINYINT(1) NOT NULL DEFAULT 1,
  `banni`            TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── TABLE ABONNEMENTS ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `abonnements` (
  `id`           INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `membre_id`    INT(11) UNSIGNED NOT NULL,
  `type`         ENUM('daily','weekend','weekly') NOT NULL,
  `date_achat`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_fin`     DATETIME DEFAULT NULL,   -- NULL = daily (expire au prochain bet posté)
  `actif`        TINYINT(1) NOT NULL DEFAULT 1,
  `montant`      DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `ref_paiement` VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_membre` (`membre_id`),
  FOREIGN KEY (`membre_id`) REFERENCES `membres`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── TABLE BETS ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `bets` (
  `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `titre`      VARCHAR(200) NOT NULL,
  `image_path` VARCHAR(300) NOT NULL,
  `type`       SET('safe','fun','live') NOT NULL DEFAULT 'safe',
  `description` TEXT DEFAULT NULL,
  `actif`      TINYINT(1) NOT NULL DEFAULT 1,
  `date_post`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── TABLE MESSAGES (messagerie membre ↔ admin) ────────────
CREATE TABLE IF NOT EXISTS `messages` (
  `id`           INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `membre_id`    INT(11) UNSIGNED NOT NULL,
  `contenu`      TEXT NOT NULL,
  `expediteur`   ENUM('membre','admin') NOT NULL,
  `date_envoi`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lu`           TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_membre` (`membre_id`),
  FOREIGN KEY (`membre_id`) REFERENCES `membres`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── TABLE TICKETS SAV ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tickets` (
  `id`           INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `membre_id`    INT(11) UNSIGNED NOT NULL,
  `sujet`        VARCHAR(200) NOT NULL,
  `statut`       ENUM('ouvert','en_cours','resolu') NOT NULL DEFAULT 'ouvert',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_membre` (`membre_id`),
  FOREIGN KEY (`membre_id`) REFERENCES `membres`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── TABLE MESSAGES TICKETS ────────────────────────────────
CREATE TABLE IF NOT EXISTS `ticket_messages` (
  `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id`  INT(11) UNSIGNED NOT NULL,
  `contenu`    TEXT NOT NULL,
  `auteur`     ENUM('membre','admin') NOT NULL,
  `date_envoi` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ticket` (`ticket_id`),
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── COMPTE ADMIN PAR DÉFAUT ───────────────────────────────
-- Mot de passe : ChangeMe2024! (à modifier dans le panel)
INSERT INTO `membres` (`nom`, `email`, `password`, `actif`) VALUES
('Admin', 'stratedgepronos@gmail.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TiWQi7g.SuGeRPzqSRnGaMRrF7n2', 1);
