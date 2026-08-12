<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\AuthenticatedUser;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Service\CreateUserInputValidator;
use App\Service\UpdateUserInputValidator;
use App\Service\UserService;

final readonly class UserController
{
    public function __construct(
        private UserService $users,
        private CreateUserInputValidator $createValidator,
        private UpdateUserInputValidator $updateValidator
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

    public function create(Request $request): Response
    {
        $input = $this->createValidator->validate(
            $request->json()
        );

        $user = $this->users->create($input);

        return Response::json(
            [
                'user' => $this->userData($user),
            ],
            201
        );
    }

    public function update(
        Request $request,
        string $id,
        AuthenticatedUser $authenticatedUser
    ): Response {
        $userId = $this->userId($id);

        $input = $this->updateValidator->validate(
            $request->json()
        );

        $user = $this->users->update(
            $userId,
            $input,
            $authenticatedUser->user->id
        );

        return Response::json([
            'user' => $this->userData($user),
        ]);
    }

    public function delete(
        string $id,
        AuthenticatedUser $authenticatedUser
    ): Response {
        $this->users->delete(
            $this->userId($id),
            $authenticatedUser->user->id
        );

        return Response::empty();
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

    private function userId(string $value): int
    {
        if (
            preg_match('/\A[1-9][0-9]*\z/', $value) !== 1
        ) {
            throw new ValidationException([
                'id' => 'Identificador de usuario invalido.',
            ]);
        }

        return (int) $value;
    }
}
