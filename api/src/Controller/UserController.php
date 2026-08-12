<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Http\Response;
use App\Service\UserService;

final readonly class UserController
{
    public function __construct(
        private UserService $users
    ) {
    }

    public function index(): Response
    {
        return Response::json([
            'users' => array_map(
                $this->userData(...),
                $this->users->findAll()
            ),
        ]);
    }

    /**
     * @return array<string, bool|int|string>
     */
    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'nome' => $user->name,
            'email' => $user->email,
            'usuario' => $user->username,
            'perfil' => $user->profile->value,
            'ativo' => $user->active,
            'criado_em' => $user->createdAt->format(DATE_ATOM),
            'atualizado_em' => $user->updatedAt->format(DATE_ATOM),
        ];
    }
}
