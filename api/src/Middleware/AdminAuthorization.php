<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Dto\AuthenticatedUser;
use App\Entity\UserProfile;
use App\Exception\ForbiddenException;

final readonly class AdminAuthorization
{
    public function authorize(
        AuthenticatedUser $authenticatedUser
    ): void {
        if (
            $authenticatedUser->user->profile
                !== UserProfile::Admin
        ) {
            throw new ForbiddenException(
                'A operacao exige perfil ADMIN.'
            );
        }
    }
}
