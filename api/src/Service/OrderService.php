<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\OrderInput;
use App\Entity\Order;
use App\Exception\OrderNotFoundException;
use App\Exception\ValidationException;
use App\Repository\ClientRepository;
use App\Repository\OrderRepository;

final readonly class OrderService
{
    public function __construct(
        private OrderRepository $repository,
        private ClientRepository $clients
    ) {
    }

    public function create(
        OrderInput $input,
        int $createdBy
    ): Order {
        $this->requireClient($input->clientId);

        return $this->repository->create(
            clientId: $input->clientId,
            description: $input->description,
            totalAmount: $input->totalAmount,
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
        $this->requireClient($input->clientId);
        $this->findById($id);

        $order = $this->repository->update(
            id: $id,
            clientId: $input->clientId,
            description: $input->description,
            totalAmount: $input->totalAmount,
            status: $input->status
        );

        if ($order === null) {
            throw new OrderNotFoundException(
                'Pedido nao encontrado.'
            );
        }

        return $order;
    }

    private function requireClient(int $clientId): void
    {
        if ($this->clients->findById($clientId) !== null) {
            return;
        }

        throw new ValidationException([
            'cliente_id' => 'Cliente selecionado nao encontrado.',
        ]);
    }
}
