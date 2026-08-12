<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Entity\User;
use App\Entity\UserProfile;
use App\Repository\UserRepository;
use DateTimeImmutable;

final class InMemoryUserRepository implements UserRepository
{
    /**
     * @var list<User>
     */
    private array $users = [];

    private int $nextId = 1;
    private ?string $lastPasswordHash = null;

    public function create(
        string $name,
        string $email,
        string $username,
        string $passwordHash,
        UserProfile $profile
    ): User {
        $now = new DateTimeImmutable();

        $user = new User(
            id: $this->nextId,
            name: $name,
            email: $email,
            username: $username,
            profile: $profile,
            active: true,
            createdAt: $now,
            updatedAt: $now
        );

        $this->nextId++;
        $this->lastPasswordHash = $passwordHash;
        $this->users[] = $user;

        return $user;
    }

    public function findById(int $id): ?User
    {
        foreach ($this->users as $user) {
            if ($user->id === $id && !$user->isDeleted()) {
                return $user;
            }
        }

        return null;
    }

    public function update(
        int $id,
        string $name,
        string $email,
        string $username,
        ?string $passwordHash,
        UserProfile $profile,
        bool $active
    ): ?User {
        foreach ($this->users as $index => $user) {
            if ($user->id !== $id || $user->isDeleted()) {
                continue;
            }

            $updatedUser = new User(
                id: $user->id,
                name: $name,
                email: $email,
                username: $username,
                profile: $profile,
                active: $active,
                createdAt: $user->createdAt,
                updatedAt: new DateTimeImmutable()
            );

            $this->users[$index] = $updatedUser;

            if ($passwordHash !== null) {
                $this->lastPasswordHash = $passwordHash;
            }

            return $updatedUser;
        }

        return null;
    }

    public function softDelete(int $id): bool
    {
        foreach ($this->users as $index => $user) {
            if ($user->id !== $id || $user->isDeleted()) {
                continue;
            }

            $now = new DateTimeImmutable();

            $this->users[$index] = new User(
                id: $user->id,
                name: $user->name,
                email: $user->email,
                username: $user->username,
                profile: $user->profile,
                active: false,
                createdAt: $user->createdAt,
                updatedAt: $now,
                deletedAt: $now
            );

            return true;
        }

        return false;
    }

    public function findByUsername(string $username): ?User
    {
        foreach ($this->users as $user) {
            if (
                $user->username === $username
                && !$user->isDeleted()
            ) {
                return $user;
            }
        }

        return null;
    }

    public function emailExists(
        string $email,
        ?int $exceptUserId = null
    ): bool
    {
        foreach ($this->users as $user) {
            if (
                $user->email === $email
                && $user->id !== $exceptUserId
            ) {
                return true;
            }
        }

        return false;
    }

    public function usernameExists(
        string $username,
        ?int $exceptUserId = null
    ): bool
    {
        foreach ($this->users as $user) {
            if (
                $user->username === $username
                && $user->id !== $exceptUserId
            ) {
                return true;
            }
        }

        return false;
    }

    public function findAll(): array
    {
        return array_values(
            array_filter(
                $this->users,
                static fn (User $user): bool =>
                    !$user->isDeleted()
            )
        );
    }

    public function lastPasswordHash(): ?string
    {
        return $this->lastPasswordHash;
    }
}
