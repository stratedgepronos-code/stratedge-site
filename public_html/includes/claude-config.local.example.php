<?php
/**
 * NE PAS METTRE DE VRAIE CLÉ ICI — ce fichier est versionné (Git).
 *
 * 1. Copie ce fichier en :  claude-config.local.php  (même dossier includes/)
 * 2. Dans claude-config.local.php uniquement : remplace TA_CLE_ICI par ta vraie clé Anthropic
 *
 * claude-config.local.php est dans .gitignore → Git ne le versionne pas.
 * Déploiement SSH (rsync --delete) : le workflow GitHub utilise une règle « protect » pour ne JAMAIS
 *   supprimer ce fichier sur le serveur (comme smtp-config.php).
 * Déploiement FTP (action GitHub) : includes/claude-config.local.php est dans exclude → pas supprimé.
 * Si tu uploades à la main : ne pas activer « supprimer les fichiers absents » sur includes/claude-config.local.php
 */
if (!defined('ABSPATH')) {
    define('ABSPATH', true);
}
define('CLAUDE_API_KEY', 'TA_CLE_ICI');
