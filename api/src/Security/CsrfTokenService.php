<?php

declare(strict_types=1);

namespace App\Security;

use InvalidArgumentException;

final class CsrfTokenService
{
    private const TOKEN_BYTES = 32;

    public function generate(): string
    {
        return bin2hex(
            random_bytes(self::TOKEN_BYTES)
        );
    }

    public function hash(string $token): string
    {
        if (!$this->isValidIdentifier($token)) {
            throw new InvalidArgumentException(
                'Token CSRF invalido.'
            );
        }

        return hash('sha256', $token);
    }

    public function verify(
        string $token,
        string $expectedHash
    ): bool {
        if (
            !$this->isValidIdentifier($token)
            || !$this->isValidIdentifier($expectedHash)
        ) {
            return false;
        }

        return hash_equals(
            $expectedHash,
            hash('sha256', $token)
        );
    }

    private function isValidIdentifier(string $value): bool
    {
        return preg_match(
            '/\A[a-f0-9]{64}\z/',
            $value
        ) === 1;
    }
}
