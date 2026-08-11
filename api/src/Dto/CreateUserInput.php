<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\UserProfile;

final readonly class CreateUserInput
{
    public function __construct(
        public string $name,
        public string $email,
        public string $username,
        public string $password,
        public UserProfile $profile
    ) {
    }
}
