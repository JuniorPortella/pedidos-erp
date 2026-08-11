<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\TokenType;

final readonly class IssuedToken
{
    public function __construct(
        public string $value,
        public string $jti,
        public TokenType $type,
        public int $issuedAt,
        public int $expiresAt,
        public ?string $familyId = null
    ) {
    }
}
