<?php

declare(strict_types=1);

namespace App\Console;

use App\Entity\User;
use App\Entity\UserProfile;
use App\Service\CreateUserInputValidator;
use App\Service\UserService;

final readonly class CreateAdminCommand
{
    public function __construct(
        private CreateUserInputValidator $validator,
        private UserService $users
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function execute(array $data): User
    {
        $data['perfil'] = UserProfile::Admin->value;

        return $this->users->create(
            $this->validator->validate($data)
        );
    }
}
