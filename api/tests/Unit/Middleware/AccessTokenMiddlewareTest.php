<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Config\AuthConfig;
use App\Entity\User;
use App\Entity\UserProfile;
use App\Exception\UnauthenticatedException;
use App\Http\AuthenticationCookieService;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\AccessTokenMiddleware;
use App\Repository\TokenBlacklistRepository;
use App\Repository\UserRepository;
use App\Service\JwtService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AccessTokenMiddlewareTest extends TestCase
{
    private JwtService $jwtService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jwtService = new JwtService(
            AuthConfig::fromEnvironment()
        );

        $this->user = $this->createUser();
    }

    public function testAuthenticatesActiveUserAndCallsNextHandler(): void
    {
        $token = $this->jwtService->issueAccessToken(
            $this->user->id,
            hash('sha256', 'csrf-token')
        );

        $blacklist = $this->createMock(
            TokenBlacklistRepository::class
        );
        $blacklist
            ->expects(self::once())
            ->method('contains')
            ->with($token->jti)
            ->willReturn(false);

        $users = $this->createMock(UserRepository::class);
        $users
            ->expects(self::once())
            ->method('findById')
            ->with($this->user->id)
            ->willReturn($this->user);

        $middleware = new AccessTokenMiddleware(
            $this->jwtService,
            $blacklist,
            $users
        );

        $response = $middleware->handle(
            $this->requestWithToken($token->value),
            function ($authenticatedUser): Response {
                self::assertSame(
                    $this->user,
                    $authenticatedUser->user
                );
                self::assertSame(
                    $this->user->id,
                    $authenticatedUser->token->userId
                );

                return Response::json(['status' => 'ok']);
            }
        );

        self::assertSame(200, $response->status());
    }

    public function testRejectsRequestWithoutAccessToken(): void
    {
        $blacklist = $this->createMock(
            TokenBlacklistRepository::class
        );
        $blacklist
            ->expects(self::never())
            ->method('contains');

        $users = $this->createMock(UserRepository::class);
        $users
            ->expects(self::never())
            ->method('findById');

        $this->expectException(
            UnauthenticatedException::class
        );

        (new AccessTokenMiddleware(
            $this->jwtService,
            $blacklist,
            $users
        ))->authenticate(new Request('GET', '/auth/me'));
    }

    public function testRejectsInvalidAccessToken(): void
    {
        $blacklist = $this->createMock(
            TokenBlacklistRepository::class
        );
        $blacklist
            ->expects(self::never())
            ->method('contains');

        $users = $this->createMock(UserRepository::class);
        $users
            ->expects(self::never())
            ->method('findById');

        $this->expectException(
            UnauthenticatedException::class
        );

        (new AccessTokenMiddleware(
            $this->jwtService,
            $blacklist,
            $users
        ))->authenticate(
            $this->requestWithToken('token-invalido')
        );
    }

    public function testRejectsRevokedAccessToken(): void
    {
        $token = $this->jwtService->issueAccessToken(
            $this->user->id,
            hash('sha256', 'csrf-token')
        );

        $blacklist = $this->createMock(
            TokenBlacklistRepository::class
        );
        $blacklist
            ->expects(self::once())
            ->method('contains')
            ->with($token->jti)
            ->willReturn(true);

        $users = $this->createMock(UserRepository::class);
        $users
            ->expects(self::never())
            ->method('findById');

        $this->expectException(
            UnauthenticatedException::class
        );

        (new AccessTokenMiddleware(
            $this->jwtService,
            $blacklist,
            $users
        ))->authenticate(
            $this->requestWithToken($token->value)
        );
    }

    #[DataProvider('unavailableUsers')]
    public function testRejectsUnavailableUser(
        string $state
    ): void {
        $token = $this->jwtService->issueAccessToken(
            $this->user->id,
            hash('sha256', 'csrf-token')
        );

        $blacklist = $this->createMock(
            TokenBlacklistRepository::class
        );
        $blacklist
            ->expects(self::once())
            ->method('contains')
            ->willReturn(false);

        $users = $this->createMock(UserRepository::class);
        $users
            ->expects(self::once())
            ->method('findById')
            ->with($this->user->id)
            ->willReturn(match ($state) {
                'missing' => null,
                'inactive' => $this->createUser(active: false),
                'deleted' => $this->createUser(
                    deletedAt: new DateTimeImmutable()
                ),
            });

        $this->expectException(
            UnauthenticatedException::class
        );

        (new AccessTokenMiddleware(
            $this->jwtService,
            $blacklist,
            $users
        ))->authenticate(
            $this->requestWithToken($token->value)
        );
    }

    public static function unavailableUsers(): array
    {
        return [
            'missing' => ['missing'],
            'inactive' => ['inactive'],
            'deleted' => ['deleted'],
        ];
    }

    private function requestWithToken(string $token): Request
    {
        return new Request(
            'GET',
            '/auth/me',
            cookies: [
                AuthenticationCookieService::ACCESS_COOKIE
                    => $token,
            ]
        );
    }

    private function createUser(
        bool $active = true,
        ?DateTimeImmutable $deletedAt = null
    ): User {
        $now = new DateTimeImmutable();

        return new User(
            id: 10,
            name: 'Usuario Operador',
            email: 'operador@example.com',
            username: 'operador',
            profile: UserProfile::Operator,
            active: $active,
            createdAt: $now,
            updatedAt: $now,
            deletedAt: $deletedAt
        );
    }
}
