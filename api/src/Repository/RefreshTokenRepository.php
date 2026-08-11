<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\IssuedToken;
use App\Dto\TokenClaims;

interface RefreshTokenRepository
{
    public function register(
        int $userId,
        IssuedToken $refreshToken
    ): void;

    public function rotate(
        TokenClaims $currentToken,
        IssuedToken $replacementToken
    ): void;

    public function revokeFamily(
        int $userId,
        string $familyId
    ): void;

    public function revokeAllForUser(
        int $userId
    ): void;

    public function deleteExpired(): int;
}
