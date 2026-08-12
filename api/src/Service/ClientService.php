<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ClientInput;
use App\Entity\Client;
use App\Exception\ClientNotFoundException;
use App\Repository\ClientRepository;

final readonly class ClientService
{
    public function __construct(
        private ClientRepository $repository
    ) {
    }

    public function create(ClientInput $input): Client
    {
        return $this->repository->create(
            $input->name,
            $input->phone
        );
    }

    /**
     * @return list<Client>
     */
    public function findAll(): array
    {
        return $this->repository->findAll();
    }

    public function update(int $id, ClientInput $input): Client
    {
        if ($this->repository->findById($id) === null) {
            throw new ClientNotFoundException(
                'Cliente nao encontrado.'
            );
        }

        $client = $this->repository->update(
            $id,
            $input->name,
            $input->phone
        );

        if ($client === null) {
            throw new ClientNotFoundException(
                'Cliente nao encontrado.'
            );
        }

        return $client;
    }

    public function delete(int $id): void
    {
        if (!$this->repository->softDelete($id)) {
            throw new ClientNotFoundException(
                'Cliente nao encontrado.'
            );
        }
    }
}
