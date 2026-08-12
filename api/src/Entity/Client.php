<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;

final readonly class Client
{
    public function __construct(
        public int $id,
        public string $name,
        public string $phone,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt
    ) {
    }
}
