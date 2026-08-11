<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;

final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $username,
        private readonly string $passwordHash,
        public readonly UserProfile $profile,
        public readonly bool $active,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
        public readonly ?DateTimeImmutable $deletedAt = null
    ) {
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify(
            $password,
            $this->passwordHash
        );
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }
}
