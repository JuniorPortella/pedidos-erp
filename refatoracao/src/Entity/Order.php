<?php

declare(strict_types=1);

namespace Refatoracao\Entity;

final readonly class Order
{
    public function __construct(
        public int $id,
        public string $customerName
    ) {
    }
}
