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
        public readonly UserProfile $profile,
        public readonly bool $active,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
        public readonly ?DateTimeImmutable $deletedAt = null
    ) {
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }
}
