<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\UpdateUserInput;
use App\Entity\UserProfile;
use App\Exception\ValidationException;

final readonly class UpdateUserInputValidator
{
    public function __construct(
        private PasswordPolicy $passwordPolicy
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function validate(array $data): UpdateUserInput
    {
        $name = $this->readTrimmedString($data, 'nome');

        $email = mb_strtolower(
            $this->readTrimmedString($data, 'email'),
            'UTF-8'
        );

        $username = mb_strtolower(
            $this->readTrimmedString($data, 'usuario'),
            'UTF-8'
        );

        $profile = UserProfile::tryFrom(
            strtoupper(
                $this->readTrimmedString($data, 'perfil')
            )
        );

        $activeValue = $data['ativo'] ?? null;
        $active = is_bool($activeValue)
            ? $activeValue
            : null;

        $passwordValue = $data['senha'] ?? null;
        $password = is_string($passwordValue)
            && $passwordValue !== ''
                ? $passwordValue
                : null;

        $errors = [];

        if ($name === '') {
            $errors['nome'] = 'Informe o nome.';
        } elseif (mb_strlen($name, 'UTF-8') > 120) {
            $errors['nome'] =
                'O nome deve possuir no maximo 120 caracteres.';
        }

        if ($email === '') {
            $errors['email'] = 'Informe o e-mail.';
        } elseif (
            mb_strlen($email, 'UTF-8') > 254
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ) {
            $errors['email'] = 'Informe um e-mail valido.';
        }

        if ($username === '') {
            $errors['usuario'] = 'Informe o usuario.';
        } elseif (
            preg_match(
                '/\A[a-z0-9._-]{3,60}\z/',
                $username
            ) !== 1
        ) {
            $errors['usuario'] =
                'O usuario deve possuir entre 3 e 60 caracteres e usar apenas letras, numeros, ponto, hifen ou sublinhado.';
        }

        if ($profile === null) {
            $errors['perfil'] =
                'O perfil deve ser ADMIN ou OPERADOR.';
        }

        if ($active === null) {
            $errors['ativo'] =
                'O campo ativo deve ser verdadeiro ou falso.';
        }

        if (
            array_key_exists('senha', $data)
            && !is_string($passwordValue)
            && $passwordValue !== null
        ) {
            $errors['senha'] = 'Informe uma senha valida.';
        } elseif ($password !== null) {
            $passwordError = $this->passwordPolicy
                ->validationError($password);

            if ($passwordError !== null) {
                $errors['senha'] = $passwordError;
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new UpdateUserInput(
            name: $name,
            email: $email,
            username: $username,
            password: $password,
            profile: $profile,
            active: $active
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function readTrimmedString(
        array $data,
        string $key
    ): string {
        $value = $data[$key] ?? null;

        return is_string($value)
            ? trim($value)
            : '';
    }
}
