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

// Charge le loader secret-free du site : définit DB_HOST/DB_NAME/DB_USER/
// DB_PASS (À JOUR, mdp rotaté) + éventuellement des SE_DB_*.
$__ck = __DIR__ . '/../../../config-keys.php';
if (is_file($__ck)) require_once $__ck;

// Complète avec le config local si présent (peut définir SE_DB_* dédiés)
$__local = __DIR__ . '/config.php';
if (is_file($__local)) require_once $__local;

// ── Résolution des credentials ──
// L'Edge Finder a sa PROPRE base (stratedge_edge) mais y accède avec le
// MÊME user que le site ('stratedge'). Ce user a un mot de passe unique,
// rotaté périodiquement (durcissement sécu). On garde donc le NOM de base
// local (SE_DB_NAME = base dédiée) mais on prend le MOT DE PASSE central
// (DB_PASS, toujours à jour) quand le user local == le user central.
// -> une rotation de mdp est suivie automatiquement, plus de désync.
if (!defined('SE_DB_HOST'))    define('SE_DB_HOST',    defined('DB_HOST') ? DB_HOST : 'localhost');
if (!defined('SE_DB_CHARSET')) define('SE_DB_CHARSET', defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');
if (!defined('SE_DEBUG'))      define('SE_DEBUG', false);

$__edge_name = defined('SE_DB_NAME') ? SE_DB_NAME : (defined('DB_NAME') ? DB_NAME : null);
$__edge_user = defined('SE_DB_USER') ? SE_DB_USER : (defined('DB_USER') ? DB_USER : null);
// mot de passe : si le user Edge == user central, on prend le mdp central
// (à jour) ; sinon on garde le SE_DB_PASS local (base réellement séparée).
if (defined('DB_USER') && defined('DB_PASS') && $__edge_user === DB_USER) {
    $__edge_pass = DB_PASS;                        // même user -> mdp central à jour
} elseif (defined('SE_DB_PASS')) {
    $__edge_pass = SE_DB_PASS;                     // user distinct -> mdp local
} elseif (defined('DB_PASS')) {
    $__edge_pass = DB_PASS;                        // dernier recours
} else {
    $__edge_pass = null;
}

class SE_Db {
    private static ?PDO $pdo = null;

    public static function pdo(): PDO {
        if (self::$pdo === null) {
            global $__edge_name, $__edge_user, $__edge_pass;
            if ($__edge_name === null || $__edge_user === null || $__edge_pass === null) {
                error_log('Edge Finder: credentials DB introuvables (ni DB_* central ni SE_DB_* local).');
                http_response_code(500);
                exit('Database connection error');
            }
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s',
                           SE_DB_HOST, $__edge_name, SE_DB_CHARSET);
            try {
                self::$pdo = new PDO($dsn, $__edge_user, $__edge_pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]);
            } catch (PDOException $e) {
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
