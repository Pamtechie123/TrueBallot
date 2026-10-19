<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/** Owns the one database connection used by the whole application. */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $c = require BASE_PATH . '/config/database.php';

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $c['host'],
                $c['port'],
                $c['database'],
                $c['charset']
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            // Secure connection for Aiven, only when the certificate file is there.
            if (!empty($c['ssl_ca']) && is_file($c['ssl_ca'])) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $c['ssl_ca'];
            }

            try {
                self::$pdo = new PDO($dsn, $c['username'], $c['password'], $options);
            } catch (PDOException $e) {
                throw new \RuntimeException('Could not connect to the database: ' . $e->getMessage(), 0, $e);
            }
        }

        return self::$pdo;
    }

    /**
     * Run several database changes as one unit: all of them succeed, or none are kept.
     */
    public static function transaction(callable $work): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();

        try {
            $result = $work();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
