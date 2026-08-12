<?php

declare(strict_types=1);

namespace App\Service;

final readonly class PasswordPolicy
{
    public function validationError(
        string $password
    ): ?string {
        if (mb_strlen($password, 'UTF-8') <= 8) {
            return 'A senha deve possuir mais de 8 caracteres.';
        }

        if (strlen($password) > 72) {
            return 'A senha deve possuir no maximo 72 bytes.';
        }

        if (
            preg_match('/\p{Lu}/u', $password) !== 1
            || preg_match('/\p{Ll}/u', $password) !== 1
            || preg_match('/\p{N}/u', $password) !== 1
            || preg_match('/[\p{P}\p{S}]/u', $password) !== 1
        ) {
            return 'A senha deve conter uma letra maiuscula, uma minuscula, um numero e um caractere especial.';
        }

        return null;
    }
}
