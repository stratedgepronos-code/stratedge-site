-- Table de log anti-bot (rate limit + monitoring)
CREATE TABLE IF NOT EXISTS antibot_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  result VARCHAR(20) NOT NULL,        -- pass | honeypot | timing | ratelimit | disposable | turnstile
  user_agent VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_ip_time (ip, created_at),
  INDEX idx_result (result)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Soft delete sur users si pas déjà présent
-- ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL;
