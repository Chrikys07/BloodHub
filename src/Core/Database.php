<?php
namespace BloodHub\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) return self::$connection;

        $root = dirname(__DIR__, 2);
        $configFile = $root . '/config/database.php';
        if (!file_exists($configFile)) $configFile = $root . '/config/database.example.php';
        if (!file_exists($configFile)) {
            throw new RuntimeException('Arquivo config/database.php não encontrado.');
        }
        $config = require $configFile;
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'], $config['port'], $config['database'], $config['charset'] ?? 'utf8mb4'
        );
        try {
            self::$connection = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Falha na conexão com o banco de dados.');
        }
        return self::$connection;
    }
}
