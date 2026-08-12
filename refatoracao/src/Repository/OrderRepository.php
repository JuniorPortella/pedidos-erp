<?php

declare(strict_types=1);

namespace Refatoracao\Repository;

use Refatoracao\Entity\Order;

interface OrderRepository
{
    public function insert(string $customerName): Order;
}
