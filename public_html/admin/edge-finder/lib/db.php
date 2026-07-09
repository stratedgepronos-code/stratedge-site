<?php
/**
 * StratEdge Edge Finder — Connexion MySQL via PDO
 *
 * ⚠️ Edge Finder a sa PROPRE base (u527192911_edge : pick_candidates,
 * match_scorer_analysis, picks_imports, pick_matches). On garde donc une
 * connexion dédiée — mais le MOT DE PASSE est chargé via le loader
 * secret-free central (config-keys.php) quand il expose SE_DB_PASS, pour
 * éviter un config dupliqué qui se désynchronise après une rotation de mdp.
 *
 * Ordre de résolution des credentials :
 *   1. constantes SE_DB_* déjà définies dans config-keys.php (hors-Git) ;
 *   2. sinon lib/config.php local (fallback historique).
 * Le message d'erreur log la cause réelle (au lieu d'un 500 muet).
 */
declare(strict_types=1);

// Charge d'abord le loader secret-free du site (peut définir SE_DB_PASS)
$__ck = __DIR__ . '/../../../config-keys.php';
if (is_file($__ck)) require_once $__ck;

// Complète avec le config local si des constantes manquent encore
if (!defined('SE_DB_PASS') || !defined('SE_DB_NAME')) {
    $__local = __DIR__ . '/config.php';
    if (is_file($__local)) require_once $__local;
}

// Derniers filets de sécurité pour les identifiants non sensibles
if (!defined('SE_DB_HOST'))    define('SE_DB_HOST', 'localhost');
if (!defined('SE_DB_CHARSET')) define('SE_DB_CHARSET', 'utf8mb4');
if (!defined('SE_DEBUG'))      define('SE_DEBUG', false);

class SE_Db {
    private static ?PDO $pdo = null;

    public static function pdo(): PDO {
        if (self::$pdo === null) {
            if (!defined('SE_DB_NAME') || !defined('SE_DB_USER') || !defined('SE_DB_PASS')) {
                error_log('Edge Finder: credentials DB manquants (ni config-keys.php ni lib/config.php ne les définissent).');
                http_response_code(500);
                exit('Database connection error');
            }
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s',
                           SE_DB_HOST, SE_DB_NAME, SE_DB_CHARSET);
            try {
                self::$pdo = new PDO($dsn, SE_DB_USER, SE_DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]);
            } catch (PDOException $e) {
                // log la vraie cause (mdp périmé après rotation, DB absente...)
                error_log('Edge Finder DB connection error: ' . $e->getMessage());
                if (SE_DEBUG) throw $e;
                http_response_code(500);
                exit('Database connection error');
            }
        }
        return self::$pdo;
    }

    /**
     * Force une reconnexion fraîche.
     * Utile après une opération longue (appel API de 60s+) durant laquelle
     * MySQL a pu fermer la connexion inactive (erreur "server has gone away").
     */
    public static function reconnect(): void {
        self::$pdo = null;
        self::pdo();
    }

    /** Exécute une requête préparée. */
    public static function execute(string $sql, array $params = []): PDOStatement {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Retourne 1 ligne ou null. */
    public static function queryOne(string $sql, array $params = []): ?array {
        $stmt = self::execute($sql, $params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Retourne toutes les lignes. */
    public static function queryAll(string $sql, array $params = []): array {
        return self::execute($sql, $params)->fetchAll();
    }

    /** Retourne le dernier ID inséré. */
    public static function lastInsertId(): int {
        return (int) self::pdo()->lastInsertId();
    }

    /** Démarre une transaction. */
    public static function begin(): void {
        self::pdo()->beginTransaction();
    }

    public static function commit(): void {
        self::pdo()->commit();
    }

    public static function rollback(): void {
        if (self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }
    }
}
