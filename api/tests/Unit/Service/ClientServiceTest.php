<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Dto\ClientInput;
use App\Exception\ClientNotFoundException;
use App\Service\ClientService;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryClientRepository;

final class ClientServiceTest extends TestCase
{
    private ClientService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ClientService(
            new InMemoryClientRepository()
        );
    }

    public function testCreatesListsUpdatesAndDeletesClient(): void
    {
        $created = $this->service->create(
            new ClientInput('Cliente', '11999999999')
        );

        self::assertSame([$created], $this->service->findAll());

        $updated = $this->service->update(
            $created->id,
            new ClientInput('Cliente Atualizado', '11888888888')
        );

        self::assertSame('Cliente Atualizado', $updated->name);
        self::assertSame('11888888888', $updated->phone);

        $this->service->delete($created->id);

        self::assertSame([], $this->service->findAll());
    }

    public function testRejectsUpdatingMissingClient(): void
    {
        $this->expectException(ClientNotFoundException::class);

        $this->service->update(
            999,
            new ClientInput('Cliente', '11999999999')
        );
    }

    public function testRejectsDeletingMissingClient(): void
    {
        $this->expectException(ClientNotFoundException::class);

        $this->service->delete(999);
    }
}
