<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;

final readonly class AuthenticationResult
{
    public function __construct(
        public User $user,
        public IssuedToken $accessToken,
        public IssuedToken $refreshToken,
        public string $csrfToken
    ) {
    }
}
