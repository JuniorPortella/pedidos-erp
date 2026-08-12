<?php

declare(strict_types=1);

namespace Tests\Support;

use Refatoracao\Entity\Order;
use Refatoracao\Repository\OrderRepository;
use RuntimeException;

final class InMemoryOrderRepository implements OrderRepository
{
    /** @var list<Order> */
    public array $orders = [];

    public bool $mustFail = false;

    public function insert(string $customerName): Order
    {
        if ($this->mustFail) {
            throw new RuntimeException('Falha simulada no banco.');
        }

        $order = new Order(
            id: count($this->orders) + 1,
            customerName: $customerName
        );

        $this->orders[] = $order;

        return $order;
    }
}
