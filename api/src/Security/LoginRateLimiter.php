<?php

declare(strict_types=1);

namespace App\Security;

interface LoginRateLimiter
{
    public function assertAllowed(
        string $username,
        string $clientIp
    ): void;

    public function registerFailure(
        string $username,
        string $clientIp
    ): void;

    public function registerSuccess(
        string $username,
        string $clientIp
    ): void;
}
