<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\TokenType;

final readonly class TokenClaims
{
    public function __construct(
        public int $userId,
        public string $jti,
        public TokenType $type,
        public int $issuedAt,
        public int $notBefore,
        public int $expiresAt,
        public ?string $familyId = null,
        public ?string $csrfHash = null
    ) {
    }
}
