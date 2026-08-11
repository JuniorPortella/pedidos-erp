<?php

declare(strict_types=1);

namespace App\Security;

use InvalidArgumentException;

final class LookupHasher
{
    private const KEY_LENGTH = 32;

    private string $key;

    public function __construct(string $encodedKey)
    {
        $key = base64_decode($encodedKey, true);

        if (
            $key === false
            || strlen($key) !== self::KEY_LENGTH
        ) {
            throw new InvalidArgumentException(
                'Chave de consulta invalida.'
            );
        }

        $this->key = $key;
    }

    public function hash(string $value, string $context): string
    {
        if ($context === '') {
            throw new InvalidArgumentException(
                'Contexto de consulta obrigatorio.'
            );
        }

        return hash_hmac(
            'sha256',
            $context . "\0" . $value,
            $this->key
        );
    }
}

