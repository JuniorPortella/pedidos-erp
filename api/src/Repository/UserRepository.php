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

    public function update(
        int $id,
        string $name,
        string $email,
        string $username,
        ?string $passwordHash,
        UserProfile $profile,
        bool $active
    ): ?User;

    public function softDelete(int $id): bool;

    public function findById(int $id): ?User;

    public function findByUsername(string $username): ?User;

    public function emailExists(
        string $email,
        ?int $exceptUserId = null
    ): bool;

    public function usernameExists(
        string $username,
        ?int $exceptUserId = null
    ): bool;

    /**
     * @return list<User>
     */
    public function findAll(): array;
}
