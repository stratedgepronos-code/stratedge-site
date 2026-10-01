<?php
// ============================================================
// STRATEDGE — Configuration base de données
// ⚠️ Ce fichier ne contient PLUS aucun secret.
//    DB_PASS et SECRET_KEY vivent dans config-keys.php (hors Git,
//    préservé par le déploiement). Ce loader peut rester versionné.
// ============================================================

// Secrets hors-Git (DB_PASS, SECRET_KEY, clés API, Turnstile, HMAC…)
$__ck = __DIR__ . '/../config-keys.php';
if (is_file($__ck)) require_once $__ck;

// Identifiants non sensibles — fallback dev via if(!defined())
if (!defined('DB_HOST'))     define('DB_HOST', 'localhost');
if (!defined('DB_NAME'))     define('DB_NAME', 'u527192911_stratedge');
if (!defined('DB_USER'))     define('DB_USER', 'u527192911_admin');
if (!defined('DB_CHARSET'))  define('DB_CHARSET', 'utf8mb4');
if (!defined('SITE_URL'))    define('SITE_URL', 'https://stratedgepronos.fr');
if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', 'stratedgepronos@gmail.com');

// Secrets OBLIGATOIRES — doivent provenir de config-keys.php
if (!defined('DB_PASS') || !defined('SECRET_KEY')) {
    error_log('db.php: DB_PASS/SECRET_KEY manquants — config-keys.php absent ou incomplet.');
    http_response_code(500);
    die('Erreur de configuration serveur.');
}

// ── Connexion PDO ─────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("DB Error: " . $e->getMessage());
            die("Erreur de connexion à la base de données. Réessayez plus tard.");
        }
    }
    return $pdo;
}
