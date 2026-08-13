<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateUserInput;
use App\Dto\UpdateUserInput;
use App\Entity\UserProfile;
use App\Entity\User;
use App\Exception\UserNotFoundException;
use App\Exception\ValidationException;
use App\Repository\RefreshTokenRepository;
use App\Repository\UserRepository;

final class UserService
{
    public function __construct(
        private readonly UserRepository $repository,
        private readonly ?RefreshTokenRepository $refreshTokens = null,
        private readonly ?int $protectedUserId = null
    ) {
    }

    public function create(
        CreateUserInput $input
    ): User {
        $errors = [];

        if ($this->repository->emailExists($input->email)) {
            $errors['email'] =
                'Este e-mail ja esta cadastrado.';
        }

        if (
            $this->repository
                ->usernameExists($input->username)
        ) {
            $errors['usuario'] =
                'Este usuario ja esta cadastrado.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $passwordHash = password_hash(
            $input->password,
            PASSWORD_DEFAULT
        );

        return $this->repository->create(
            name: $input->name,
            email: $input->email,
            username: $input->username,
            passwordHash: $passwordHash,
            profile: $input->profile
        );
    }

    /**
     * @return list<User>
     */
    public function findAll(): array
    {
        return array_values(
            array_filter(
                $this->repository->findAll(),
                fn (User $user): bool =>
                    !$this->isProtected($user->id)
            )
        );
    }

    public function findById(int $id): ?User
    {
        if ($this->isProtected($id)) {
            return null;
        }

        return $this->repository->findById($id);
    }

    public function update(
        int $id,
        UpdateUserInput $input,
        int $actorId
    ): User {
        $this->ensureNotProtected($id);

        $currentUser = $this->repository->findById($id);

        if ($currentUser === null) {
            throw new UserNotFoundException(
                'Usuario nao encontrado.'
            );
        }

        $errors = [];

        if (
            $this->repository->emailExists(
                $input->email,
                $id
            )
        ) {
            $errors['email'] =
                'Este e-mail ja esta cadastrado.';
        }

        if (
            $this->repository->usernameExists(
                $input->username,
                $id
            )
        ) {
            $errors['usuario'] =
                'Este usuario ja esta cadastrado.';
        }

        if ($id === $actorId && !$input->active) {
            $errors['ativo'] =
                'O administrador nao pode desativar a propria conta.';
        }

        if (
            $id === $actorId
            && $input->profile !== UserProfile::Admin
        ) {
            $errors['perfil'] =
                'O administrador nao pode remover o proprio perfil ADMIN.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $passwordHash = $input->password === null
            ? null
            : password_hash(
                $input->password,
                PASSWORD_DEFAULT
            );

        $updatedUser = $this->repository->update(
            id: $id,
            name: $input->name,
            email: $input->email,
            username: $input->username,
            passwordHash: $passwordHash,
            profile: $input->profile,
            active: $input->active
        );

        if ($updatedUser === null) {
            throw new UserNotFoundException(
                'Usuario nao encontrado.'
            );
        }

        if ($input->password !== null || !$input->active) {
            $this->refreshTokens?->revokeAllForUser($id);
        }

        return $updatedUser;
    }

    public function delete(int $id, int $actorId): void
    {
        $this->ensureNotProtected($id);

        if ($id === $actorId) {
            throw new ValidationException([
                'id' =>
                    'O administrador nao pode excluir a propria conta.',
            ]);
        }

        if ($this->repository->findById($id) === null) {
            throw new UserNotFoundException(
                'Usuario nao encontrado.'
            );
        }

        if (!$this->repository->softDelete($id)) {
            throw new UserNotFoundException(
                'Usuario nao encontrado.'
            );
        }

        $this->refreshTokens?->revokeAllForUser($id);
    }

    private function ensureNotProtected(int $id): void
    {
        if ($this->isProtected($id)) {
            throw new UserNotFoundException(
                'Usuario nao encontrado.'
            );
        }
    }

    private function isProtected(int $id): bool
    {
        return $this->protectedUserId !== null
            && $id === $this->protectedUserId;
    }
}
