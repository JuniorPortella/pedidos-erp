<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ClientInput;
use App\Entity\Client;
use App\Exception\ClientNotFoundException;
use App\Exception\ValidationException;
use App\Repository\ClientRepository;

final readonly class ClientService
{
    public function __construct(
        private ClientRepository $repository
    ) {
    }

    public function create(ClientInput $input): Client
    {
        $this->ensurePhoneIsAvailable($input->phone);

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

        $this->ensurePhoneIsAvailable($input->phone, $id);

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

    private function ensurePhoneIsAvailable(
        string $phone,
        ?int $exceptClientId = null
    ): void {
        if (
            $this->repository->phoneExists(
                $phone,
                $exceptClientId
            )
        ) {
            throw new ValidationException([
                'telefone' => 'Este telefone ja esta cadastrado.',
            ]);
        }
    }
}
