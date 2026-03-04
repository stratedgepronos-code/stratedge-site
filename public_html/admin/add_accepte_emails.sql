-- ============================================================
-- STRATEDGE — Préférence emails (RGPD / LCEN / désinscription)
-- À exécuter une fois en base
-- ============================================================

-- accepte_emails : 1 = accepte notifications (bets, résultats, messages), 0 = désinscrit
-- DEFAULT 1 pour les membres existants (on continue d'envoyer jusqu'à désinscription)
ALTER TABLE `membres`
  ADD COLUMN `accepte_emails` TINYINT(1) NOT NULL DEFAULT 1
  COMMENT '1=accepte emails notif, 0=désinscrit'
  AFTER `banni`;

-- Index optionnel pour filtrer les envois
-- CREATE INDEX idx_accepte_emails ON membres(accepte_emails);
