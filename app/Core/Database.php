<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $config = require BASE_PATH . '/config/database.php';

            // Reject DSN separators in configuration values.
            foreach (['host', 'port', 'database', 'charset'] as $key) {
                if (!is_string($config[$key]) || preg_match('/[;\x00-\x1F]/', $config[$key])) {
                    throw new RuntimeException('Invalid database configuration.');
                }
            }

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );

            self::$connection = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
            self::$connection->exec("SET time_zone = '+00:00'");
        }

        return self::$connection;
    }
}
