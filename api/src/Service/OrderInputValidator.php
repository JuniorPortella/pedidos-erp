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
        $clientId = $this->readPositiveInteger(
            $data['cliente_id'] ?? null
        );

        $description = $this->readTrimmedString(
            $data,
            'descricao'
        );

        $totalAmount = $this->normalizeMoney(
            $data['valor_total'] ?? null
        );

        $statusValue = strtoupper(
            $this->readTrimmedString($data, 'status')
        );

        $status = OrderStatus::tryFrom($statusValue);
        $errors = [];

        if ($clientId === null) {
            $errors['cliente_id'] =
                'Selecione um cliente.';
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

        if ($totalAmount === null) {
            $errors['valor_total'] =
                'Informe um valor maior que zero com no maximo duas casas decimais.';
        }

        if ($status === null) {
            $errors['status'] =
                'O status deve ser PENDENTE, EM_PROCESSAMENTO ou CONCLUIDO.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new OrderInput(
            clientId: $clientId,
            description: $description,
            totalAmount: $totalAmount,
            status: $status
        );
    }

    private function normalizeMoney(mixed $value): ?string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (
            preg_match(
                '/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/',
                $value
            ) !== 1
        ) {
            return null;
        }

        [$integer, $decimal] = array_pad(
            explode('.', $value, 2),
            2,
            ''
        );
        $decimal = str_pad($decimal, 2, '0');

        if ($integer === '0' && $decimal === '00') {
            return null;
        }

        return $integer . '.' . $decimal;
    }

    private function readPositiveInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (
            !is_string($value)
            || preg_match('/\A[1-9][0-9]*\z/', $value) !== 1
        ) {
            return null;
        }

        $integer = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        return is_int($integer) ? $integer : null;
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
