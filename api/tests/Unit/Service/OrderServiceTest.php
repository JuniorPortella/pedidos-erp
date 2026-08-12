<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Dto\OrderInput;
use App\Entity\OrderStatus;
use App\Exception\OrderNotFoundException;
use App\Service\OrderService;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryOrderRepository;

final class OrderServiceTest extends TestCase
{
    private InMemoryOrderRepository $repository;
    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new InMemoryOrderRepository();
        $this->service = new OrderService($this->repository);
    }

    public function testCreatesOrderForAuthenticatedUser(): void
    {
        $order = $this->service->create(
            new OrderInput(
                'Cliente',
                'Descricao',
                OrderStatus::Pending
            ),
            42
        );

        self::assertSame(1, $order->id);
        self::assertSame(42, $order->createdBy);
        self::assertSame(
            OrderStatus::Pending,
            $order->status
        );
    }

    public function testListsAndFindsOrders(): void
    {
        $created = $this->service->create(
            new OrderInput(
                'Cliente',
                'Descricao',
                OrderStatus::Pending
            ),
            10
        );

        self::assertSame(
            $created,
            $this->service->findById($created->id)
        );
        self::assertSame(
            [$created],
            $this->service->findAll()
        );
    }

    public function testUpdatesExistingOrder(): void
    {
        $created = $this->service->create(
            new OrderInput(
                'Cliente',
                'Descricao',
                OrderStatus::Pending
            ),
            10
        );

        $updated = $this->service->update(
            $created->id,
            new OrderInput(
                'Cliente Atualizado',
                'Descricao Atualizada',
                OrderStatus::Completed
            )
        );

        self::assertSame(
            'Cliente Atualizado',
            $updated->customerName
        );
        self::assertSame(
            OrderStatus::Completed,
            $updated->status
        );
        self::assertSame(10, $updated->createdBy);
    }

    public function testRejectsMissingOrder(): void
    {
        $this->expectException(
            OrderNotFoundException::class
        );

        $this->service->findById(999);
    }

    public function testRejectsUpdatingMissingOrder(): void
    {
        $this->expectException(
            OrderNotFoundException::class
        );

        $this->service->update(
            999,
            new OrderInput(
                'Cliente',
                'Descricao',
                OrderStatus::Pending
            )
        );
    }
}
