<?php
/**
 * NE PAS METTRE DE VRAIE CLÉ ICI — ce fichier est versionné (Git).
 *
 * 1. Copie ce fichier en :  claude-config.local.php  (même dossier includes/)
 * 2. Dans claude-config.local.php uniquement : remplace TA_CLE_ICI par ta vraie clé Anthropic
 *
 * claude-config.local.php est dans .gitignore → git pull ne le modifie JAMAIS, ta clé ne disparaît plus.
 * Sur le serveur FTP : après déploiement, crée claude-config.local.php (copie de ce .example) puis mets ta clé dedans.
 */
if (!defined('ABSPATH')) {
    define('ABSPATH', true);
}
define('CLAUDE_API_KEY', 'sk-ant-api03-TA_CLE_ICI');
