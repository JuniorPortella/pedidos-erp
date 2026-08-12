<?php

declare(strict_types=1);

namespace Refatoracao\Database;

use PDO;
use RuntimeException;

final class ConnectionFactory
{
    private function __construct()
    {
    }

    public static function fromEnvironment(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            self::required('REFACTOR_DB_HOST'),
            self::required('REFACTOR_DB_PORT'),
            self::required('REFACTOR_DB_DATABASE')
        );

        return new PDO(
            $dsn,
            self::required('REFACTOR_DB_USERNAME'),
            self::required('REFACTOR_DB_PASSWORD'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    private static function required(string $name): string
    {
        $value = getenv($name);

        if ($value === false || trim($value) === '') {
            throw new RuntimeException(
                sprintf(
                    'Variavel de ambiente obrigatoria nao configurada: %s',
                    $name
                )
            );
        }

        return $value;
    }
}
