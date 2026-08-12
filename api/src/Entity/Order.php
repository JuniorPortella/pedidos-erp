<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;

final readonly class Order
{
    public function __construct(
        public int $id,
        public string $customerName,
        public string $description,
        public OrderStatus $status,
        public int $createdBy,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt
    ) {
    }
}
