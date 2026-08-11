<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;

final class Environment
{
    private function __construct()
    {
    }

    public static function getRequired(string $name): string
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

    public static function getBoolean(string $name): bool
    {
        $rawValue = self::getRequired($name);

        $value = filter_var(
            $rawValue,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );

        if ($value === null) {
            throw new RuntimeException(
                sprintf(
                    'Variavel de ambiente booleana invalida: %s',
                    $name
                )
            );
        }

        return $value;
    }
}
