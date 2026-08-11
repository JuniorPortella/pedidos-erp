<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateUserInput;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Repository\UserRepository;

final class UserService
{
    public function __construct(
        private readonly UserRepository $repository
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
        return $this->repository->findAll();
    }

    public function findById(int $id): ?User
    {
        return $this->repository->findById($id);
    }
}
