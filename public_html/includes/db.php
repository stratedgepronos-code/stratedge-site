<?php
// ============================================================
// STRATEDGE — Configuration base de données
// ⚠️ Ne JAMAIS partager ce fichier / ne pas le mettre sur GitHub
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'u527192911_stratedge');
define('DB_USER', 'u527192911_admin');
define('DB_PASS', 'StrEdge2024!xK');
define('DB_CHARSET', 'utf8mb4');

// URL de base du site
define('SITE_URL', 'https://stratedgepronos.fr');

// Email admin
define('ADMIN_EMAIL', 'stratedgepronos@gmail.com');

// Clé secrète pour les tokens (CHANGE cette valeur en prod !)
define('SECRET_KEY', 'X9k2mP7vQ3nR8sT1uW4yZ6a0bC5dE2fG');

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
            // En prod, ne jamais afficher l'erreur
            error_log("DB Error: " . $e->getMessage());
            die("Erreur de connexion à la base de données. Réessayez plus tard.");
        }
    }
    return $pdo;
}
