<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ClientInput;
use App\Exception\ValidationException;

final readonly class ClientInputValidator
{
    /**
     * @param array<string, mixed> $data
     */
    public function validate(array $data): ClientInput
    {
        $name = $this->readTrimmedString($data, 'nome');
        $phone = $this->readTrimmedString($data, 'telefone');
        $errors = [];

        if ($name === '') {
            $errors['nome'] = 'Informe o nome do cliente.';
        } elseif (mb_strlen($name, 'UTF-8') > 120) {
            $errors['nome'] =
                'O nome deve possuir no maximo 120 caracteres.';
        }

        if ($phone === '') {
            $errors['telefone'] = 'Informe o telefone do cliente.';
        } elseif (mb_strlen($phone, 'UTF-8') > 20) {
            $errors['telefone'] =
                'O telefone deve possuir no maximo 20 caracteres.';
        } elseif (
            preg_match('/\A[0-9+().\-\s]+\z/u', $phone) !== 1
        ) {
            $errors['telefone'] = 'Informe um telefone valido.';
        } else {
            $digits = preg_replace('/\D+/', '', $phone);

            if (
                !is_string($digits)
                || strlen($digits) < 8
                || strlen($digits) > 15
            ) {
                $errors['telefone'] = 'Informe um telefone valido.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new ClientInput($name, $phone);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function readTrimmedString(
        array $data,
        string $key
    ): string {
        $value = $data[$key] ?? null;

        return is_string($value) ? trim($value) : '';
    }
}
