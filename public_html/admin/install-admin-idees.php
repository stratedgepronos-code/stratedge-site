<?php
/**
 * Création des tables admin_idees et admin_inbox (appelé une fois au premier chargement).
 * Ne pas appeler directement — inclus par idees.php et messagerie-interne.php.
 */

if (!defined('ADMIN_IDEES_TABLES_CREATED')) {
    $db = getDB();
    $db->exec("
        CREATE TABLE IF NOT EXISTS `admin_idees` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `admin_id` int(11) unsigned NOT NULL COMMENT 'membre_id de l\'admin qui a soumis',
            `type` enum('idee','bug') NOT NULL,
            `titre` varchar(255) NOT NULL,
            `description` text NOT NULL,
            `statut` enum('en_attente','accepte','refuse','en_cours','termine') NOT NULL DEFAULT 'en_attente',
            `progression_pct` tinyint(3) unsigned NOT NULL DEFAULT 0,
            `notes_super` text DEFAULT NULL,
            `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `date_maj` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_admin` (`admin_id`),
            KEY `idx_statut` (`statut`),
            KEY `idx_type` (`type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS `admin_inbox` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `type` enum('idee','bug') NOT NULL,
            `ref_id` int(11) unsigned NOT NULL COMMENT 'admin_idees.id',
            `titre` varchar(255) NOT NULL,
            `contenu` text NOT NULL,
            `lu` tinyint(1) NOT NULL DEFAULT 0,
            `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_ref` (`ref_id`),
            KEY `idx_lu` (`lu`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    define('ADMIN_IDEES_TABLES_CREATED', true);
}
