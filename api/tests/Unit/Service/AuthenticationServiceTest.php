<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Config\AuthConfig;
use App\Dto\IssuedToken;
use App\Entity\User;
use App\Entity\UserProfile;
use App\Exception\InvalidCredentialsException;
use App\Repository\AuthenticationRepository;
use App\Repository\RefreshTokenRepository;
use App\Security\CsrfTokenService;
use App\Service\AuthenticationService;
use App\Service\JwtService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthenticationServiceTest extends TestCase
{
    private JwtService $jwtService;
    private CsrfTokenService $csrfService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jwtService = new JwtService(
            AuthConfig::fromEnvironment()
        );

        $this->csrfService = new CsrfTokenService();
    }

    public function testLogsInWithValidCredentials(): void
    {
        $user = $this->createUser();

        $authentication = $this->createMock(
            AuthenticationRepository::class
        );

        $authentication
            ->expects(self::once())
            ->method('authenticate')
            ->with('operador', 'SenhaSegura123')
            ->willReturn($user);

        $registeredRefresh = null;

        $refreshTokens = $this->createMock(
            RefreshTokenRepository::class
        );

        $refreshTokens
            ->expects(self::once())
            ->method('register')
            ->with(
                $user->id,
                self::callback(
                    static function (
                        IssuedToken $token
                    ) use (&$registeredRefresh): bool {
                        $registeredRefresh = $token;

                        return true;
                    }
                )
            );

        $service = new AuthenticationService(
            $authentication,
            $this->jwtService,
            $this->csrfService,
            $refreshTokens
        );

        $result = $service->login(
            ' operador ',
            'SenhaSegura123'
        );

        self::assertSame($user, $result->user);
        self::assertSame(
            $registeredRefresh,
            $result->refreshToken
        );

        $accessClaims = $this->jwtService
            ->decodeAccessToken($result->accessToken->value);

        $refreshClaims = $this->jwtService
            ->decodeRefreshToken($result->refreshToken->value);

        self::assertSame($user->id, $accessClaims->userId);
        self::assertSame($user->id, $refreshClaims->userId);
        self::assertSame(
            $result->refreshToken->familyId,
            $refreshClaims->familyId
        );
        self::assertTrue(
            $this->csrfService->verify(
                $result->csrfToken,
                $accessClaims->csrfHash
            )
        );
    }

    public function testRejectsInvalidCredentials(): void
    {
        $authentication = $this->createMock(
            AuthenticationRepository::class
        );

        $authentication
            ->expects(self::once())
            ->method('authenticate')
            ->with('operador', 'SenhaIncorreta')
            ->willReturn(null);

        $refreshTokens = $this->createMock(
            RefreshTokenRepository::class
        );

        $refreshTokens
            ->expects(self::never())
            ->method('register');

        $service = new AuthenticationService(
            $authentication,
            $this->jwtService,
            $this->csrfService,
            $refreshTokens
        );

        $this->expectException(
            InvalidCredentialsException::class
        );
        $this->expectExceptionMessage(
            'Usuario ou senha invalidos.'
        );

        $service->login('operador', 'SenhaIncorreta');
    }

    #[DataProvider('emptyCredentials')]
    public function testRejectsEmptyCredentials(
        string $username,
        string $password
    ): void {
        $authentication = $this->createMock(
            AuthenticationRepository::class
        );

        $authentication
            ->expects(self::never())
            ->method('authenticate');

        $refreshTokens = $this->createMock(
            RefreshTokenRepository::class
        );

        $refreshTokens
            ->expects(self::never())
            ->method('register');

        $service = new AuthenticationService(
            $authentication,
            $this->jwtService,
            $this->csrfService,
            $refreshTokens
        );

        $this->expectException(
            InvalidCredentialsException::class
        );

        $service->login($username, $password);
    }

    public static function emptyCredentials(): array
    {
        return [
            'empty username' => ['', 'SenhaSegura123'],
            'blank username' => ['   ', 'SenhaSegura123'],
            'empty password' => ['operador', ''],
        ];
    }

    private function createUser(): User
    {
        $now = new DateTimeImmutable();

        return new User(
            id: 10,
            name: 'Usuario Operador',
            email: 'operador@example.com',
            username: 'operador',
            profile: UserProfile::Operator,
            active: true,
            createdAt: $now,
            updatedAt: $now
        );
    }
}
