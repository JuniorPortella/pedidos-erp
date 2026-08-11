<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserProfile;

interface UserRepository
{
    public function create(
        string $name,
        string $email,
        string $username,
        string $passwordHash,
        UserProfile $profile
    ): User;

    public function findById(int $id): ?User;

    public function findByUsername(string $username): ?User;

    public function emailExists(string $email): bool;

    public function usernameExists(string $username): bool;

    /**
     * @return list<User>
     */
    public function findAll(): array;
}
