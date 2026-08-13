<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Dto\ClientInput;
use App\Exception\ClientNotFoundException;
use App\Exception\ValidationException;
use App\Service\ClientService;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryClientRepository;

final class ClientServiceTest extends TestCase
{
    private InMemoryClientRepository $repository;
    private ClientService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new InMemoryClientRepository();
        $this->service = new ClientService($this->repository);
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

    public function testRejectsDuplicatedNormalizedPhone(): void
    {
        $this->service->create(
            new ClientInput(
                'Primeiro Cliente',
                '+55 (11) 99999-9999'
            )
        );

        try {
            $this->service->create(
                new ClientInput(
                    'Segundo Cliente',
                    '11999999999'
                )
            );

            self::fail('Era esperada uma ValidationException.');
        } catch (ValidationException $exception) {
            self::assertSame(
                'Este telefone ja esta cadastrado.',
                $exception->errors()['telefone'] ?? null
            );
        }
    }

    public function testRejectsChangingPhoneToAnotherClientsPhone(): void
    {
        $first = $this->service->create(
            new ClientInput('Primeiro Cliente', '11999999999')
        );
        $this->service->create(
            new ClientInput('Segundo Cliente', '11888888888')
        );

        $this->expectException(ValidationException::class);

        $this->service->update(
            $first->id,
            new ClientInput(
                'Primeiro Cliente',
                '+55 (11) 88888-8888'
            )
        );
    }

    public function testAllowsOwnPhoneAndReuseAfterDeletion(): void
    {
        $client = $this->service->create(
            new ClientInput('Cliente', '11999999999')
        );

        $updated = $this->service->update(
            $client->id,
            new ClientInput(
                'Cliente Atualizado',
                '+55 (11) 99999-9999'
            )
        );

        self::assertSame(
            '+55 (11) 99999-9999',
            $updated->phone
        );

        $this->service->delete($client->id);

        $replacement = $this->service->create(
            new ClientInput('Novo Cliente', '11999999999')
        );

        self::assertSame('Novo Cliente', $replacement->name);
    }
}
