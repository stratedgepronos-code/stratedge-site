<?php
/**
 * CONFIG KEYS — StratEdge Pronos
 * ================================
 * Copie ce fichier en "config-keys.php" (à la racine de public_html/) et remplis les clés.
 * Hors Git : le déploiement SSH (rsync) préserve ce fichier sur le serveur — il n’est pas écrasé ni supprimé.
 * Première fois : envoie-le une fois (FTP/SSH), puis il reste en place.
 */

define('ANTHROPIC_API_KEY', 'sk-ant-api03-REPLACE-ME');
define('FOOTYSTATS_API_KEY', 'REPLACE-ME');
define('ODDS_API_KEY', 'REPLACE-ME');

/** SportMonks (plugin public_html/plugins/football_sportmonks/) — sync + BDD fd_sm_* */
define('SPORTMONKS_API_TOKEN', '');

/**
 * Optionnel : token dédié pour plugins/football_sportmonks/football_internal_api.php
 * Si vide, c’est AUTH_TOKEN qui est utilisé (comme stats-api.php).
 */
define('FOOTBALL_CONTEXT_TOKEN', '');

// Token d'authentification pour les endpoints publics (odds-api, stats-api, claude-api)
// Génère un token fort : python3 -c "import secrets; print(secrets.token_hex(24))"
// NE JAMAIS mettre le même token que dans scanner-app.js ou le code source
define('AUTH_TOKEN', 'REPLACE-ME-STRONG-TOKEN');
?>
