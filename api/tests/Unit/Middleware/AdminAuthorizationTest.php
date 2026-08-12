<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Dto\AuthenticatedUser;
use App\Dto\TokenClaims;
use App\Entity\TokenType;
use App\Entity\User;
use App\Entity\UserProfile;
use App\Exception\ForbiddenException;
use App\Middleware\AdminAuthorization;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AdminAuthorizationTest extends TestCase
{
    public function testAllowsAdminUser(): void
    {
        $authorization = new AdminAuthorization();

        $authorization->authorize(
            $this->authenticatedUser(UserProfile::Admin)
        );

        self::addToAssertionCount(1);
    }

    public function testRejectsOperatorUser(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage(
            'A operacao exige perfil ADMIN.'
        );

        (new AdminAuthorization())->authorize(
            $this->authenticatedUser(UserProfile::Operator)
        );
    }

    private function authenticatedUser(
        UserProfile $profile
    ): AuthenticatedUser {
        $now = new DateTimeImmutable();

        return new AuthenticatedUser(
            new User(
                id: 10,
                name: 'Usuario',
                email: 'usuario@example.com',
                username: 'usuario',
                profile: $profile,
                active: true,
                createdAt: $now,
                updatedAt: $now
            ),
            new TokenClaims(
                userId: 10,
                jti: str_repeat('a', 64),
                type: TokenType::Access,
                issuedAt: 1_000,
                notBefore: 1_000,
                expiresAt: 2_000,
                csrfHash: str_repeat('b', 64)
            )
        );
    }
}
