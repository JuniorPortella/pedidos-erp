<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateUserInput;
use App\Entity\UserProfile;
use App\Exception\ValidationException;

final class CreateUserInputValidator
{
    /**
     * @param array<string, mixed> $data
     */
    public function validate(array $data): CreateUserInput
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

        $passwordValue = $data['senha'] ?? null;
        $password = is_string($passwordValue)
            ? $passwordValue
            : '';

        $profileValue = array_key_exists('perfil', $data)
            ? strtoupper(
                $this->readTrimmedString($data, 'perfil')
            )
            : UserProfile::Operator->value;

        $profile = UserProfile::tryFrom($profileValue);

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

        if ($password === '') {
            $errors['senha'] = 'Informe a senha.';
        } elseif (mb_strlen($password, 'UTF-8') < 8) {
            $errors['senha'] =
                'A senha deve possuir pelo menos 8 caracteres.';
        } elseif (strlen($password) > 72) {
            $errors['senha'] =
                'A senha deve possuir no maximo 72 bytes.';
        }

        if ($profile === null) {
            $errors['perfil'] =
                'O perfil deve ser ADMIN ou OPERADOR.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new CreateUserInput(
            name: $name,
            email: $email,
            username: $username,
            password: $password,
            profile: $profile
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
