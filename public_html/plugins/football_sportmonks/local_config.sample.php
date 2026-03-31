<?php
/**
 * Copier en local_config.php (non versionné, voir .gitignore).
 * Optionnel si tout est déjà dans public_html/config-keys.php et includes/db.php.
 *
 * Ce fichier est dans : public_html/plugins/football_sportmonks/
 */

// Forcer une PDO dédiée (sinon le plugin utilise includes/db.php → getDB()) :
// $GLOBALS['fd_pdo'] = new PDO('mysql:host=localhost;dbname=…;charset=utf8mb4', 'user', 'pass', [
//     PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
//     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
// ]);

// Si tu ne mets pas SPORTMONKS_API_TOKEN dans config-keys.php :
// define('SPORTMONKS_API_TOKEN', 'votre_token_sportmonks');
