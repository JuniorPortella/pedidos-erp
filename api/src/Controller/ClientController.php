<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Client;
use App\Exception\ValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Service\ClientInputValidator;
use App\Service\ClientService;

final readonly class ClientController
{
    public function __construct(
        private ClientService $clients,
        private ClientInputValidator $validator
    ) {
    }

    public function index(): Response
    {
        return Response::json([
            'clients' => array_map(
                $this->clientData(...),
                $this->clients->findAll()
            ),
        ]);
    }

    public function create(Request $request): Response
    {
        $client = $this->clients->create(
            $this->validator->validate($request->json())
        );

        return Response::json(
            ['client' => $this->clientData($client)],
            201
        );
    }

    public function update(Request $request, string $id): Response
    {
        $client = $this->clients->update(
            $this->clientId($id),
            $this->validator->validate($request->json())
        );

        return Response::json([
            'client' => $this->clientData($client),
        ]);
    }

    public function delete(string $id): Response
    {
        $this->clients->delete($this->clientId($id));

        return Response::empty();
    }

    /**
     * @return array<string, int|string>
     */
    private function clientData(Client $client): array
    {
        return [
            'id' => $client->id,
            'nome' => $client->name,
            'telefone' => $client->phone,
            'created_at' => $client->createdAt->format(DATE_ATOM),
            'updated_at' => $client->updatedAt->format(DATE_ATOM),
        ];
    }

    private function clientId(string $value): int
    {
        if (preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
            throw new ValidationException([
                'id' => 'Identificador de cliente invalido.',
            ]);
        }

        return (int) $value;
    }
}
