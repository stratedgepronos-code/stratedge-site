<?php
/**
 * StratEdge Edge Finder — Template de configuration
 *
 * COMMENT UTILISER :
 *   1. Copier ce fichier sous le nom config.php (dans le même dossier)
 *   2. Remplir les valeurs réelles (DB, token)
 *   3. config.php est gitignored : il ne sera jamais committé
 *   4. Sur Hostinger, config.php est protégé par exclusion rsync
 *      (--exclude='public_html/admin/edge-finder/lib/config.php' dans le workflow)
 *
 * SÉCURITÉ : Ne JAMAIS committer config.php. Si un secret fuite, régénérer
 * immédiatement le token et le password.
 */
declare(strict_types=1);

// =============================================================================
// MySQL Hostinger
// =============================================================================

const SE_DB_HOST = 'localhost';
const SE_DB_NAME = 'NOM_EXACT_DE_TA_DB';      // ex: u527192911_u527192911_edge
const SE_DB_USER = 'NOM_EXACT_DU_USER_MYSQL'; // ex: u527192911_edge
const SE_DB_PASS = 'TON_MOT_DE_PASSE_DB';
const SE_DB_CHARSET = 'utf8mb4';

// =============================================================================
// Token d'authentification pour l'import de picks
// =============================================================================

// Généré via : python -c "import secrets; print(secrets.token_hex(32))"
const SE_IMPORT_TOKEN = 'TOKEN_HEX_64_CARACTERES';

// =============================================================================
// Paramètres dashboard
// =============================================================================

const SE_TZ_DISPLAY  = 'Europe/Paris';
const SE_BACKUP_JSON = true;
const SE_BACKUP_DIR  = __DIR__ . '/../data/imports';
const SE_LOG_FILE    = __DIR__ . '/../data/import.log';

// =============================================================================
// Debug — false en production, true pour voir les erreurs PHP
// =============================================================================

const SE_DEBUG = false;

if (SE_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ERROR | E_PARSE);
    ini_set('display_errors', '0');
}

date_default_timezone_set('UTC');
