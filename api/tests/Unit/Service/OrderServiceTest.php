<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Dto\OrderInput;
use App\Entity\OrderStatus;
use App\Exception\OrderNotFoundException;
use App\Service\OrderService;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryOrderRepository;
use Tests\Support\InMemoryClientRepository;

final class OrderServiceTest extends TestCase
{
    private InMemoryOrderRepository $repository;
    private int $clientId;
    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $clients = new InMemoryClientRepository();
        $this->clientId = $clients->create(
            'Cliente',
            '11999999999'
        )->id;
        $this->repository = new InMemoryOrderRepository();
        $this->service = new OrderService(
            $this->repository,
            $clients
        );
    }

    public function testCreatesOrderForAuthenticatedUser(): void
    {
        $order = $this->service->create(
            new OrderInput(
                $this->clientId,
                'Descricao',
                OrderStatus::Pending
            ),
            42
        );

        self::assertSame(1, $order->id);
        self::assertSame($this->clientId, $order->clientId);
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
                $this->clientId,
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
                $this->clientId,
                'Descricao',
                OrderStatus::Pending
            ),
            10
        );

        $updated = $this->service->update(
            $created->id,
            new OrderInput(
                $this->clientId,
                'Descricao Atualizada',
                OrderStatus::Completed
            )
        );

        self::assertSame($this->clientId, $updated->clientId);
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
                $this->clientId,
                'Descricao',
                OrderStatus::Pending
            )
        );
    }

    public function testRejectsMissingClient(): void
    {
        $this->expectException(\App\Exception\ValidationException::class);

        $this->service->create(
            new OrderInput(
                999,
                'Descricao',
                OrderStatus::Pending
            ),
            10
        );
    }
}
