<?php

declare(strict_types=1);

namespace App\Database;

use App\Config\Environment;
use PDO;

final class ConnectionFactory
{
    private function __construct()
    {
    }

    public static function create(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            Environment::getRequired('DB_HOST'),
            Environment::getRequired('DB_PORT'),
            Environment::getRequired('DB_DATABASE')
        );

        return new PDO(
            $dsn,
            Environment::getRequired('DB_USERNAME'),
            Environment::getRequired('DB_PASSWORD'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
}
