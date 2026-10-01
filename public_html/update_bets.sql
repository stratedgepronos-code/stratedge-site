-- ============================================================
-- STRATEDGE — Mise à jour BDD : résultats des bets
-- À exécuter dans phpMyAdmin > onglet SQL
-- ============================================================

ALTER TABLE bets 
  ADD COLUMN resultat ENUM('en_cours','gagne','perdu','annule') NOT NULL DEFAULT 'en_cours' AFTER actif,
  ADD COLUMN date_resultat DATETIME NULL AFTER resultat;
