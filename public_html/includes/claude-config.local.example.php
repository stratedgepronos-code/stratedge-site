<?php
/**
 * 1. Copie ce fichier :  claude-config.local.php  (même dossier)
 * 2. Colle ta vraie clé API Anthropic à la place de TA_CLE_ICI
 *
 * claude-config.local.php est dans .gitignore → git pull ne le modifie JAMAIS.
 * Sur le serveur : crée ce fichier une fois après déploiement si les cards Claude ne marchent pas.
 */
if (!defined('ABSPATH')) {
    define('ABSPATH', true);
}
define('CLAUDE_API_KEY', 'sk-ant-api03-TA_CLE_ICI');
