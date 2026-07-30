-- ============================================================
-- STRATEDGE ODDS ENGINE v1 — fondation CLV / boosts / lag detector
-- ============================================================

-- Événements sportifs suivis (matchs)
CREATE TABLE IF NOT EXISTS oe_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(30) NOT NULL,              -- 'theoddsapi' | 'oddspapi' | ...
  provider_event_id VARCHAR(64) NOT NULL,
  sport_key VARCHAR(60) NOT NULL,             -- ex: soccer_france_ligue_one, tennis_atp
  home VARCHAR(120) NOT NULL,
  away VARCHAR(120) NOT NULL,
  commence_time DATETIME NOT NULL,
  status ENUM('open','closing_captured','done') NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_provider_event (provider, provider_event_id),
  KEY idx_commence (commence_time, status),
  KEY idx_sport (sport_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Snapshots de cotes : UNE ligne par changement détecté (pas par poll)
-- => l'historique complet des mouvements, compact
CREATE TABLE IF NOT EXISTS oe_odds (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id INT UNSIGNED NOT NULL,
  book VARCHAR(40) NOT NULL,                  -- ex: betclic, unibet_eu, pinnacle
  market VARCHAR(30) NOT NULL,                -- h2h | spreads | totals
  selection VARCHAR(120) NOT NULL,            -- nom équipe | Over | Under
  line DECIMAL(6,2) NULL,                     -- handicap/total (NULL pour h2h)
  odds DECIMAL(8,3) NOT NULL,
  captured_at DATETIME NOT NULL,
  is_opening TINYINT(1) NOT NULL DEFAULT 0,   -- 1ere cote vue pour ce (book,market,selection,line)
  is_closing TINYINT(1) NOT NULL DEFAULT 0,   -- derniere cote avant le coup d'envoi
  KEY idx_lookup (event_id, book, market, selection, captured_at),
  KEY idx_closing (event_id, is_closing),
  CONSTRAINT fk_oe_odds_event FOREIGN KEY (event_id) REFERENCES oe_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CLV : lie un bet StratEdge a la cloture du marche
CREATE TABLE IF NOT EXISTS oe_clv (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bet_id INT UNSIGNED NOT NULL,
  event_id INT UNSIGNED NOT NULL,
  market VARCHAR(30) NOT NULL,
  selection VARCHAR(120) NOT NULL,
  line DECIMAL(6,2) NULL,
  odds_taken DECIMAL(8,3) NOT NULL,           -- cote frozen du pick
  closing_odds DECIMAL(8,3) NULL,             -- cloture book de reference
  closing_fair DECIMAL(8,3) NULL,             -- cloture devigee (proba juste)
  clv_pct DECIMAL(6,2) NULL,                  -- (odds_taken/closing_fair - 1) * 100
  ref_book VARCHAR(40) NULL,
  computed_at DATETIME NULL,
  UNIQUE KEY uq_bet (bet_id),
  KEY idx_event (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Liaison bets -> odds engine (remplie a la creation du pick ou a posteriori)
-- ALTER TABLE bets ADD COLUMN oe_event_id INT UNSIGNED NULL DEFAULT NULL;
-- ALTER TABLE bets ADD COLUMN oe_market VARCHAR(30) NULL DEFAULT NULL;
-- ALTER TABLE bets ADD COLUMN oe_selection VARCHAR(120) NULL DEFAULT NULL;
