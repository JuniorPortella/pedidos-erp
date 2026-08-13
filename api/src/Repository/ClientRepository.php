<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Client;

interface ClientRepository
{
    public function create(string $name, string $phone): Client;

    public function update(
        int $id,
        string $name,
        string $phone
    ): ?Client;

    public function softDelete(int $id): bool;

    public function findById(int $id): ?Client;

    public function phoneExists(
        string $phone,
        ?int $exceptClientId = null
    ): bool;

    /**
     * @return list<Client>
     */
    public function findAll(): array;
}
