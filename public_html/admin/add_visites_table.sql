-- Table pour le compteur de visites (plugin visiteurs) — visiteurs UNIQUES
-- À exécuter une fois en base si la table n'existe pas
CREATE TABLE IF NOT EXISTS `visites` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `visitor_id` VARCHAR(64) DEFAULT NULL COMMENT 'Hash IP+User-Agent pour déduplication',
  `t`  INT(11) UNSIGNED NOT NULL COMMENT 'Unix timestamp de la visite',
  PRIMARY KEY (`id`),
  KEY `idx_t` (`t`),
  KEY `idx_visitor_t` (`visitor_id`, `t`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
