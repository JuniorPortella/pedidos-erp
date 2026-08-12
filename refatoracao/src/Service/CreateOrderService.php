<?php

declare(strict_types=1);

namespace Refatoracao\Service;

use Refatoracao\Entity\Order;
use Refatoracao\Exception\OrderCreationException;
use Refatoracao\Exception\ValidationException;
use Refatoracao\Logging\Logger;
use Refatoracao\Repository\OrderRepository;
use Throwable;

final readonly class CreateOrderService
{
    public function __construct(
        private OrderRepository $orders,
        private Logger $logger
    ) {
    }

    public function create(string $customerName): Order
    {
        $customerName = trim($customerName);

        if ($customerName === '') {
            throw new ValidationException(
                'O nome do cliente e obrigatorio.'
            );
        }

        if (mb_strlen($customerName, 'UTF-8') > 120) {
            throw new ValidationException(
                'O nome do cliente deve possuir no maximo 120 caracteres.'
            );
        }

        try {
            $order = $this->orders->insert($customerName);

            $this->logger->info(
                'Pedido criado com sucesso.',
                ['order_id' => $order->id]
            );

            return $order;
        } catch (Throwable $exception) {
            $this->logger->error(
                'Erro ao criar pedido.',
                [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]
            );

            throw new OrderCreationException(
                'Nao foi possivel criar o pedido.',
                0,
                $exception
            );
        }
    }
}
