<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;

final readonly class AuthConfig
{
    private function __construct(
        public string $accessSecret,
        public string $refreshSecret,
        public int $accessTtl,
        public int $refreshTtl,
        public string $issuer,
        public string $audience,
        public bool $cookieSecure,
        public string $cookieSameSite,
        public bool $csrfEnabled
    ) {
    }

    public static function fromEnvironment(): self
    {
        $appEnvironment = strtolower(
            Environment::getRequired('APP_ENV')
        );

        $appDebug = Environment::getBoolean(
            'APP_DEBUG'
        );

        if (
            !in_array(
                $appEnvironment,
                ['development', 'test', 'production'],
                true
            )
        ) {
            throw new RuntimeException(
                'APP_ENV deve ser development, test ou production.'
            );
        }

        $accessSecret = self::decodeSecret(
            'JWT_ACCESS_SECRET'
        );

        $refreshSecret = self::decodeSecret(
            'JWT_REFRESH_SECRET'
        );

        if (hash_equals($accessSecret, $refreshSecret)) {
            throw new RuntimeException(
                'As chaves de access e refresh devem ser diferentes.'
            );
        }

        $accessTtl = Environment::getPositiveInteger(
            'JWT_ACCESS_TTL'
        );

        $refreshTtl = Environment::getPositiveInteger(
            'JWT_REFRESH_TTL'
        );

        if ($accessTtl >= $refreshTtl) {
            throw new RuntimeException(
                'O TTL do access token deve ser menor que o TTL do refresh token.'
            );
        }

        $cookieSecure = Environment::getBoolean(
            'AUTH_COOKIE_SECURE'
        );

        $csrfEnabled = Environment::getBoolean(
            'AUTH_CSRF_ENABLED'
        );

        $cookieSameSite = match (
            strtolower(
                Environment::getRequired(
                    'AUTH_COOKIE_SAME_SITE'
                )
            )
        ) {
            'lax' => 'Lax',
            'strict' => 'Strict',
            'none' => 'None',
            default => throw new RuntimeException(
                'AUTH_COOKIE_SAME_SITE deve ser Lax, Strict ou None.'
            ),
        };

        if (
            $cookieSameSite === 'None'
            && !$cookieSecure
        ) {
            throw new RuntimeException(
                'SameSite=None exige cookies Secure.'
            );
        }

        if ($appEnvironment === 'production') {
            if ($appDebug) {
                throw new RuntimeException(
                    'APP_DEBUG deve ser false em producao.'
                );
            }

            if (!$cookieSecure) {
                throw new RuntimeException(
                    'Cookies de autenticacao devem ser Secure em producao.'
                );
            }

            if (!$csrfEnabled) {
                throw new RuntimeException(
                    'A protecao CSRF deve estar ativa em producao.'
                );
            }
        }

        return new self(
            accessSecret: $accessSecret,
            refreshSecret: $refreshSecret,
            accessTtl: $accessTtl,
            refreshTtl: $refreshTtl,
            issuer: Environment::getRequired(
                'JWT_ISSUER'
            ),
            audience: Environment::getRequired(
                'JWT_AUDIENCE'
            ),
            cookieSecure: $cookieSecure,
            cookieSameSite: $cookieSameSite,
            csrfEnabled: $csrfEnabled
        );
    }

    private static function decodeSecret(
        string $name
    ): string {
        $secret = base64_decode(
            Environment::getRequired($name),
            true
        );

        if (
            $secret === false
            || strlen($secret) < 32
        ) {
            throw new RuntimeException(
                sprintf(
                    'A chave %s deve possuir pelo menos 32 bytes em Base64.',
                    $name
                )
            );
        }

        return $secret;
    }
}
