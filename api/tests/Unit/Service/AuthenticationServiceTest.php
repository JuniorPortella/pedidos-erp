<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Config\AuthConfig;
use App\Dto\IssuedToken;
use App\Dto\TokenClaims;
use App\Entity\TokenRevocationReason;
use App\Entity\User;
use App\Entity\UserProfile;
use App\Exception\InvalidCredentialsException;
use App\Exception\InvalidTokenException;
use App\Exception\RefreshTokenReuseException;
use App\Repository\AuthenticationRepository;
use App\Repository\RefreshTokenRepository;
use App\Repository\TokenBlacklistRepository;
use App\Repository\UserRepository;
use App\Security\CsrfTokenService;
use App\Service\AuthenticationService;
use App\Service\JwtService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
            $refreshTokens,
            $this->createMock(UserRepository::class),
            $this->createMock(TokenBlacklistRepository::class)
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
            $refreshTokens,
            $this->createMock(UserRepository::class),
            $this->createMock(TokenBlacklistRepository::class)
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
            $refreshTokens,
            $this->createMock(UserRepository::class),
            $this->createMock(TokenBlacklistRepository::class)
        );

        $this->expectException(
            InvalidCredentialsException::class
        );

        $service->login($username, $password);
    }

    public function testRefreshesTokensWithinSameFamily(): void
    {
        $user = $this->createUser();
        $currentRefresh = $this->jwtService
            ->issueRefreshToken($user->id);
        $currentClaims = $this->jwtService
            ->decodeRefreshToken($currentRefresh->value);

        $authentication = $this->createMock(
            AuthenticationRepository::class
        );

        $authentication
            ->expects(self::never())
            ->method('authenticate');

        $users = $this->createMock(
            UserRepository::class
        );

        $users
            ->expects(self::once())
            ->method('findById')
            ->with($user->id)
            ->willReturn($user);

        $replacementRefresh = null;

        $refreshTokens = $this->createMock(
            RefreshTokenRepository::class
        );

        $refreshTokens
            ->expects(self::once())
            ->method('rotate')
            ->with(
                self::callback(
                    static fn (TokenClaims $claims): bool =>
                        $claims->jti === $currentClaims->jti
                        && $claims->familyId
                            === $currentClaims->familyId
                ),
                self::callback(
                    static function (
                        IssuedToken $token
                    ) use (&$replacementRefresh): bool {
                        $replacementRefresh = $token;

                        return true;
                    }
                )
            );

        $service = new AuthenticationService(
            $authentication,
            $this->jwtService,
            $this->csrfService,
            $refreshTokens,
            $users,
            $this->createMock(TokenBlacklistRepository::class)
        );

        $result = $service->refresh(
            $currentRefresh->value
        );

        self::assertSame($user, $result->user);
        self::assertSame(
            $replacementRefresh,
            $result->refreshToken
        );
        self::assertNotSame(
            $currentRefresh->jti,
            $result->refreshToken->jti
        );
        self::assertSame(
            $currentRefresh->familyId,
            $result->refreshToken->familyId
        );

        $accessClaims = $this->jwtService
            ->decodeAccessToken($result->accessToken->value);
        $replacementClaims = $this->jwtService
            ->decodeRefreshToken($result->refreshToken->value);

        self::assertSame($user->id, $accessClaims->userId);
        self::assertSame(
            $user->id,
            $replacementClaims->userId
        );
        self::assertSame(
            $currentClaims->familyId,
            $replacementClaims->familyId
        );
        self::assertTrue(
            $this->csrfService->verify(
                $result->csrfToken,
                $accessClaims->csrfHash
            )
        );
    }

    public function testRejectsRefreshForInactiveUser(): void
    {
        $user = $this->createUser(active: false);
        $currentRefresh = $this->jwtService
            ->issueRefreshToken($user->id);

        $users = $this->createMock(
            UserRepository::class
        );

        $users
            ->expects(self::once())
            ->method('findById')
            ->with($user->id)
            ->willReturn($user);

        $refreshTokens = $this->createMock(
            RefreshTokenRepository::class
        );

        $refreshTokens
            ->expects(self::once())
            ->method('revokeAllForUser')
            ->with($user->id);
        $refreshTokens
            ->expects(self::never())
            ->method('rotate');

        $service = $this->createRefreshService(
            $refreshTokens,
            $users
        );

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage(
            'Token invalido ou expirado.'
        );

        $service->refresh($currentRefresh->value);
    }

    public function testRejectsRefreshForMissingUser(): void
    {
        $currentRefresh = $this->jwtService
            ->issueRefreshToken(99);

        $users = $this->createMock(
            UserRepository::class
        );

        $users
            ->expects(self::once())
            ->method('findById')
            ->with(99)
            ->willReturn(null);

        $refreshTokens = $this->createMock(
            RefreshTokenRepository::class
        );

        $refreshTokens
            ->expects(self::once())
            ->method('revokeAllForUser')
            ->with(99);
        $refreshTokens
            ->expects(self::never())
            ->method('rotate');

        $service = $this->createRefreshService(
            $refreshTokens,
            $users
        );

        $this->expectException(InvalidTokenException::class);

        $service->refresh($currentRefresh->value);
    }

    #[DataProvider('invalidRefreshTokens')]
    public function testRejectsInvalidRefreshToken(
        string $refreshToken
    ): void {
        $users = $this->createMock(
            UserRepository::class
        );

        $users
            ->expects(self::never())
            ->method('findById');

        $refreshTokens = $this->createMock(
            RefreshTokenRepository::class
        );

        $refreshTokens
            ->expects(self::never())
            ->method('rotate');
        $refreshTokens
            ->expects(self::never())
            ->method('revokeAllForUser');

        $service = $this->createRefreshService(
            $refreshTokens,
            $users
        );

        $this->expectException(InvalidTokenException::class);

        $service->refresh($refreshToken);
    }

    public static function invalidRefreshTokens(): array
    {
        return [
            'empty token' => [''],
            'malformed token' => ['token-invalido'],
        ];
    }

    public function testRejectsAccessTokenDuringRefresh(): void
    {
        $accessToken = $this->jwtService->issueAccessToken(
            10,
            hash('sha256', 'csrf-de-teste')
        );

        $service = $this->createRefreshService(
            $this->createMock(RefreshTokenRepository::class),
            $this->createMock(UserRepository::class)
        );

        $this->expectException(InvalidTokenException::class);

        $service->refresh($accessToken->value);
    }

    public function testPropagatesRefreshTokenReuseDetection(): void
    {
        $user = $this->createUser();
        $currentRefresh = $this->jwtService
            ->issueRefreshToken($user->id);

        $users = $this->createMock(
            UserRepository::class
        );

        $users
            ->method('findById')
            ->willReturn($user);

        $refreshTokens = $this->createMock(
            RefreshTokenRepository::class
        );

        $refreshTokens
            ->expects(self::once())
            ->method('rotate')
            ->willThrowException(
                new RefreshTokenReuseException(
                    'Reutilizacao de refresh token detectada.'
                )
            );

        $service = $this->createRefreshService(
            $refreshTokens,
            $users
        );

        $this->expectException(
            RefreshTokenReuseException::class
        );

        $service->refresh($currentRefresh->value);
    }

    public function testLogsOutValidSession(): void
    {
        $accessToken = $this->jwtService->issueAccessToken(
            10,
            hash('sha256', 'csrf-de-logout')
        );
        $refreshToken = $this->jwtService
            ->issueRefreshToken(10);

        $refreshTokens = $this->createMock(
            RefreshTokenRepository::class
        );
        $refreshTokens
            ->expects(self::once())
            ->method('revokeFamily')
            ->with(10, $refreshToken->familyId);

        $blacklist = $this->createMock(
            TokenBlacklistRepository::class
        );
        $blacklist
            ->expects(self::once())
            ->method('add')
            ->with(
                self::callback(
                    static fn (TokenClaims $claims): bool =>
                        $claims->jti === $accessToken->jti
                        && $claims->userId === 10
                ),
                TokenRevocationReason::Logout
            );

        $this->createLogoutService(
            $refreshTokens,
            $blacklist
        )->logout(
            $accessToken->value,
            $refreshToken->value
        );
    }

    public function testLogoutRevokesRefreshWhenAccessIsInvalid(): void
    {
        $refreshToken = $this->jwtService
            ->issueRefreshToken(10);

        $refreshTokens = $this->createMock(
            RefreshTokenRepository::class
        );
        $refreshTokens
            ->expects(self::once())
            ->method('revokeFamily')
            ->with(10, $refreshToken->familyId);

        $blacklist = $this->createMock(
            TokenBlacklistRepository::class
        );
        $blacklist
            ->expects(self::never())
            ->method('add');

        $this->createLogoutService(
            $refreshTokens,
            $blacklist
        )->logout(
            'access-invalido',
            $refreshToken->value
        );
    }

    public function testLogoutBlocksAccessWhenRefreshIsInvalid(): void
    {
        $accessToken = $this->jwtService->issueAccessToken(
            10,
            hash('sha256', 'csrf-de-logout')
        );

        $refreshTokens = $this->createMock(
            RefreshTokenRepository::class
        );
        $refreshTokens
            ->expects(self::never())
            ->method('revokeFamily');

        $blacklist = $this->createMock(
            TokenBlacklistRepository::class
        );
        $blacklist
            ->expects(self::once())
            ->method('add')
            ->with(
                self::callback(
                    static fn (TokenClaims $claims): bool =>
                        $claims->jti === $accessToken->jti
                ),
                TokenRevocationReason::Logout
            );

        $this->createLogoutService(
            $refreshTokens,
            $blacklist
        )->logout(
            $accessToken->value,
            'refresh-invalido'
        );
    }

    #[DataProvider('emptyOrInvalidLogoutTokens')]
    public function testLogoutIsIdempotentWithInvalidTokens(
        ?string $accessToken,
        ?string $refreshToken
    ): void {
        $refreshTokens = $this->createMock(
            RefreshTokenRepository::class
        );
        $refreshTokens
            ->expects(self::never())
            ->method('revokeFamily');

        $blacklist = $this->createMock(
            TokenBlacklistRepository::class
        );
        $blacklist
            ->expects(self::never())
            ->method('add');

        $this->createLogoutService(
            $refreshTokens,
            $blacklist
        )->logout($accessToken, $refreshToken);
    }

    public static function emptyOrInvalidLogoutTokens(): array
    {
        return [
            'missing tokens' => [null, null],
            'empty tokens' => ['', ''],
            'invalid tokens' => [
                'access-invalido',
                'refresh-invalido',
            ],
        ];
    }

    public function testLogoutPropagatesRepositoryFailure(): void
    {
        $accessToken = $this->jwtService->issueAccessToken(
            10,
            hash('sha256', 'csrf-de-logout')
        );
        $refreshToken = $this->jwtService
            ->issueRefreshToken(10);

        $refreshTokens = $this->createMock(
            RefreshTokenRepository::class
        );
        $refreshTokens
            ->expects(self::once())
            ->method('revokeFamily')
            ->willThrowException(
                new RuntimeException('Falha no banco.')
            );

        $blacklist = $this->createMock(
            TokenBlacklistRepository::class
        );
        $blacklist
            ->expects(self::never())
            ->method('add');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Falha no banco.');

        $this->createLogoutService(
            $refreshTokens,
            $blacklist
        )->logout(
            $accessToken->value,
            $refreshToken->value
        );
    }

    public static function emptyCredentials(): array
    {
        return [
            'empty username' => ['', 'SenhaSegura123'],
            'blank username' => ['   ', 'SenhaSegura123'],
            'empty password' => ['operador', ''],
        ];
    }

    private function createRefreshService(
        RefreshTokenRepository $refreshTokens,
        UserRepository $users
    ): AuthenticationService {
        return new AuthenticationService(
            $this->createMock(AuthenticationRepository::class),
            $this->jwtService,
            $this->csrfService,
            $refreshTokens,
            $users,
            $this->createMock(TokenBlacklistRepository::class)
        );
    }

    private function createLogoutService(
        RefreshTokenRepository $refreshTokens,
        TokenBlacklistRepository $blacklist
    ): AuthenticationService {
        return new AuthenticationService(
            $this->createMock(AuthenticationRepository::class),
            $this->jwtService,
            $this->csrfService,
            $refreshTokens,
            $this->createMock(UserRepository::class),
            $blacklist
        );
    }

    private function createUser(bool $active = true): User
    {
        $now = new DateTimeImmutable();

        return new User(
            id: 10,
            name: 'Usuario Operador',
            email: 'operador@example.com',
            username: 'operador',
            profile: UserProfile::Operator,
            active: $active,
            createdAt: $now,
            updatedAt: $now
        );
    }
}
