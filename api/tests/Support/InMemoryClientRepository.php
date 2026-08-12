<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Entity\Client;
use App\Repository\ClientRepository;
use DateTimeImmutable;

final class InMemoryClientRepository implements ClientRepository
{
    /**
     * @var list<Client>
     */
    private array $clients = [];
    private int $nextId = 1;

    public function create(string $name, string $phone): Client
    {
        $now = new DateTimeImmutable();
        $client = new Client(
            $this->nextId++,
            $name,
            $phone,
            $now,
            $now
        );

        $this->clients[] = $client;

        return $client;
    }

    public function update(
        int $id,
        string $name,
        string $phone
    ): ?Client {
        foreach ($this->clients as $index => $client) {
            if ($client->id !== $id) {
                continue;
            }

            $updated = new Client(
                $id,
                $name,
                $phone,
                $client->createdAt,
                new DateTimeImmutable()
            );

            $this->clients[$index] = $updated;

            return $updated;
        }

        return null;
    }

    public function softDelete(int $id): bool
    {
        foreach ($this->clients as $index => $client) {
            if ($client->id !== $id) {
                continue;
            }

            array_splice($this->clients, $index, 1);

            return true;
        }

        return false;
    }

    public function findById(int $id): ?Client
    {
        foreach ($this->clients as $client) {
            if ($client->id === $id) {
                return $client;
            }
        }

        return null;
    }

    public function findAll(): array
    {
        return array_reverse($this->clients);
    }
}
