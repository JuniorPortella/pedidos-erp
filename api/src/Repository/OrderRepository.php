<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\OrderStatus;

interface OrderRepository
{
    public function create(
        int $clientId,
        string $description,
        OrderStatus $status,
        int $createdBy
    ): Order;

    public function update(
        int $id,
        int $clientId,
        string $description,
        OrderStatus $status
    ): ?Order;

    public function findById(int $id): ?Order;

    /**
     * @return list<Order>
     */
    public function findAll(): array;
}
