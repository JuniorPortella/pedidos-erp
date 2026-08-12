<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;

final readonly class AuthenticatedUser
{
    public function __construct(
        public User $user,
        public TokenClaims $token
    ) {
    }
}
