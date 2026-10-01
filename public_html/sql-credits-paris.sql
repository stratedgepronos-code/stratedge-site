-- STRATEDGE — Système de crédits paris (à vie)
-- À exécuter dans phpMyAdmin

CREATE TABLE IF NOT EXISTS credits_paris (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  membre_id INT UNSIGNED NOT NULL,
  nb_initial INT UNSIGNED NOT NULL,
  nb_restants INT UNSIGNED NOT NULL,
  pack_type VARCHAR(20) NOT NULL,
  prix_paye DECIMAL(6,2) NOT NULL,
  methode VARCHAR(20) NOT NULL,
  transaction_ref VARCHAR(100) DEFAULT NULL,
  date_achat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_membre (membre_id),
  INDEX idx_restants (membre_id, nb_restants),
  INDEX idx_date (date_achat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS credits_consommation (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  membre_id INT UNSIGNED NOT NULL,
  bet_id INT UNSIGNED NOT NULL,
  credit_pack_id INT UNSIGNED NOT NULL,
  date_conso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_membre_bet (membre_id, bet_id),
  INDEX idx_membre (membre_id),
  INDEX idx_bet (bet_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
