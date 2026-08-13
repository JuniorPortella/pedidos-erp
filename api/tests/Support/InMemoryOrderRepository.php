<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Entity\Order;
use App\Entity\OrderStatus;
use App\Repository\OrderRepository;
use DateTimeImmutable;

final class InMemoryOrderRepository implements OrderRepository
{
    /**
     * @var list<Order>
     */
    private array $orders = [];
    private int $nextId = 1;

    public function create(
        int $clientId,
        string $description,
        string $totalAmount,
        OrderStatus $status,
        int $createdBy
    ): Order {
        $now = new DateTimeImmutable();

        $order = new Order(
            id: $this->nextId++,
            clientId: $clientId,
            description: $description,
            totalAmount: $totalAmount,
            status: $status,
            createdBy: $createdBy,
            createdAt: $now,
            updatedAt: $now
        );

        $this->orders[] = $order;

        return $order;
    }

    public function update(
        int $id,
        int $clientId,
        string $description,
        string $totalAmount,
        OrderStatus $status
    ): ?Order {
        foreach ($this->orders as $index => $order) {
            if ($order->id !== $id) {
                continue;
            }

            $updatedOrder = new Order(
                id: $order->id,
                clientId: $clientId,
                description: $description,
                totalAmount: $totalAmount,
                status: $status,
                createdBy: $order->createdBy,
                createdAt: $order->createdAt,
                updatedAt: new DateTimeImmutable()
            );

            $this->orders[$index] = $updatedOrder;

            return $updatedOrder;
        }

        return null;
    }

    public function findById(int $id): ?Order
    {
        foreach ($this->orders as $order) {
            if ($order->id === $id) {
                return $order;
            }
        }

        return null;
    }

    public function findAll(): array
    {
        return array_reverse($this->orders);
    }
}
