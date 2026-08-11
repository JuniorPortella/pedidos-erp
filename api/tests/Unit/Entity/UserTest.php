<?php

declare(strict_types=1);

namespace Tests\Unit\Entity;

use App\Entity\User;
use App\Entity\UserProfile;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    private const PASSWORD = 'SenhaSegura@123';

    public function testRepresentsUserData(): void
    {
        $createdAt = new DateTimeImmutable(
            '2026-08-11 10:00:00'
        );

        $updatedAt = new DateTimeImmutable(
            '2026-08-11 11:00:00'
        );

        $user = $this->createUser(
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        self::assertSame(1, $user->id);
        self::assertSame('Vagner Portella', $user->name);
        self::assertSame('vagner@example.com', $user->email);
        self::assertSame('vagner', $user->username);
        self::assertSame(UserProfile::Admin, $user->profile);
        self::assertTrue($user->active);
        self::assertSame($createdAt, $user->createdAt);
        self::assertSame($updatedAt, $user->updatedAt);
        self::assertNull($user->deletedAt);
    }

    public function testVerifiesPassword(): void
    {
        $user = $this->createUser();

        self::assertTrue(
            $user->verifyPassword(self::PASSWORD)
        );

        self::assertFalse(
            $user->verifyPassword('SenhaIncorreta')
        );
    }

    public function testIdentifiesLogicalDeletion(): void
    {
        $activeUser = $this->createUser();

        $deletedUser = $this->createUser(
            deletedAt: new DateTimeImmutable(
                '2026-08-11 12:00:00'
            )
        );

        self::assertFalse($activeUser->isDeleted());
        self::assertTrue($deletedUser->isDeleted());
    }

    private function createUser(
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
        ?DateTimeImmutable $deletedAt = null
    ): User {
        return new User(
            id: 1,
            name: 'Vagner Portella',
            email: 'vagner@example.com',
            username: 'vagner',
            passwordHash: password_hash(
                self::PASSWORD,
                PASSWORD_DEFAULT
            ),
            profile: UserProfile::Admin,
            active: true,
            createdAt: $createdAt ?? new DateTimeImmutable(),
            updatedAt: $updatedAt ?? new DateTimeImmutable(),
            deletedAt: $deletedAt
        );
    }
}
