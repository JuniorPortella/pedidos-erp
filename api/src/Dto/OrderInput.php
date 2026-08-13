<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\OrderStatus;

final readonly class OrderInput
{
    public function __construct(
        public int $clientId,
        public string $description,
        public string $totalAmount,
        public OrderStatus $status
    ) {
    }
}
