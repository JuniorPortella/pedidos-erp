<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\TokenClaims;
use App\Entity\TokenRevocationReason;

interface TokenBlacklistRepository
{
    public function add(
        TokenClaims $token,
        TokenRevocationReason $reason
    ): void;

    public function contains(string $jti): bool;

    public function deleteExpired(): int;
}
