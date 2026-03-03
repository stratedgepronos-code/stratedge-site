-- Table pour le compteur de visites (plugin visiteurs)
-- À exécuter une fois en base si la table n'existe pas
CREATE TABLE IF NOT EXISTS `visites` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `t`  INT(11) UNSIGNED NOT NULL COMMENT 'Unix timestamp de la visite',
  PRIMARY KEY (`id`),
  KEY `idx_t` (`t`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
