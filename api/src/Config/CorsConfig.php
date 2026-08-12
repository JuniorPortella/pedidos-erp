<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;

final readonly class CorsConfig
{
    public function __construct(
        public string $allowedOrigin
    ) {
        self::validateOrigin($allowedOrigin);
    }

    public static function fromEnvironment(): self
    {
        $origin = Environment::getRequired(
            'FRONTEND_ORIGIN'
        );

        $appEnvironment = strtolower(
            Environment::getRequired('APP_ENV')
        );

        if (
            $appEnvironment === 'production'
            && !str_starts_with($origin, 'https://')
        ) {
            throw new RuntimeException(
                'FRONTEND_ORIGIN deve usar HTTPS em producao.'
            );
        }

        return new self($origin);
    }

    private static function validateOrigin(
        string $origin
    ): void {
        if (
            $origin === ''
            || trim($origin) !== $origin
            || $origin === '*'
        ) {
            throw new RuntimeException(
                'FRONTEND_ORIGIN deve conter uma origem valida.'
            );
        }

        $parts = parse_url($origin);

        if (
            $parts === false
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(
                strtolower((string) $parts['scheme']),
                ['http', 'https'],
                true
            )
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (
                isset($parts['path'])
                && $parts['path'] !== ''
            )
        ) {
            throw new RuntimeException(
                'FRONTEND_ORIGIN deve conter uma origem valida.'
            );
        }
    }
}
