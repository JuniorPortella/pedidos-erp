<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\OrderInput;
use App\Entity\OrderStatus;
use App\Exception\ValidationException;

final readonly class OrderInputValidator
{
    /**
     * @param array<string, mixed> $data
     */
    public function validate(array $data): OrderInput
    {
        $customerName = $this->readTrimmedString(
            $data,
            'cliente_nome'
        );

        $description = $this->readTrimmedString(
            $data,
            'descricao'
        );

        $statusValue = strtoupper(
            $this->readTrimmedString($data, 'status')
        );

        $status = OrderStatus::tryFrom($statusValue);
        $errors = [];

        if ($customerName === '') {
            $errors['cliente_nome'] =
                'Informe o nome do cliente.';
        } elseif (
            mb_strlen($customerName, 'UTF-8') > 120
        ) {
            $errors['cliente_nome'] =
                'O nome do cliente deve possuir no maximo 120 caracteres.';
        }

        if ($description === '') {
            $errors['descricao'] =
                'Informe a descricao do pedido.';
        } elseif (
            mb_strlen($description, 'UTF-8') > 5000
        ) {
            $errors['descricao'] =
                'A descricao deve possuir no maximo 5000 caracteres.';
        }

        if ($status === null) {
            $errors['status'] =
                'O status deve ser PENDENTE, EM_PROCESSAMENTO ou CONCLUIDO.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new OrderInput(
            customerName: $customerName,
            description: $description,
            status: $status
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
