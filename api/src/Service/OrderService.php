<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\OrderInput;
use App\Entity\Order;
use App\Exception\OrderNotFoundException;
use App\Repository\OrderRepository;

final readonly class OrderService
{
    public function __construct(
        private OrderRepository $repository
    ) {
    }

    public function create(
        OrderInput $input,
        int $createdBy
    ): Order {
        return $this->repository->create(
            customerName: $input->customerName,
            description: $input->description,
            status: $input->status,
            createdBy: $createdBy
        );
    }

    /**
     * @return list<Order>
     */
    public function findAll(): array
    {
        return $this->repository->findAll();
    }

    public function findById(int $id): Order
    {
        $order = $this->repository->findById($id);

        if ($order === null) {
            throw new OrderNotFoundException(
                'Pedido nao encontrado.'
            );
        }

        return $order;
    }

    public function update(
        int $id,
        OrderInput $input
    ): Order {
        $this->findById($id);

        $order = $this->repository->update(
            id: $id,
            customerName: $input->customerName,
            description: $input->description,
            status: $input->status
        );

        if ($order === null) {
            throw new OrderNotFoundException(
                'Pedido nao encontrado.'
            );
        }

        return $order;
    }
}
