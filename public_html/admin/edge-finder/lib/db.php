<?php
/**
 * StratEdge Edge Finder — Connexion MySQL via PDO
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

class SE_Db {
    private static ?PDO $pdo = null;

    public static function pdo(): PDO {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                SE_DB_HOST, SE_DB_NAME, SE_DB_CHARSET
            );
            try {
                self::$pdo = new PDO($dsn, SE_DB_USER, SE_DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]);
            } catch (PDOException $e) {
                if (SE_DEBUG) {
                    throw $e;
                }
                http_response_code(500);
                exit('Database connection error');
            }
        }
        return self::$pdo;
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
