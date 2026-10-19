<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class Connection
{
    public static function create(array $config): PDO
    {
        $dsn = 'mysql:host=' . $config['host'] . ';port=' . $config['port']
            . ';dbname=' . $config['name'] . ';charset=utf8mb4';
        return new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
        ]);
    }
}
