<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;

interface AuthenticationRepository
{
    public function authenticate(
        string $username,
        string $password
    ): ?User;
}
