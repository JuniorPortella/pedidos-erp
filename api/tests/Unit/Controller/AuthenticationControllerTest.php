<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Config\AuthConfig;
use App\Controller\AuthenticationController;
use App\Dto\AuthenticatedUser;
use App\Dto\IssuedToken;
use App\Dto\TokenClaims;
use App\Entity\TokenRevocationReason;
use App\Entity\User;
use App\Entity\UserProfile;
use App\Exception\InvalidJsonBodyException;
use App\Exception\InvalidCsrfTokenException;
use App\Exception\InvalidTokenException;
use App\Exception\ValidationException;
use App\Http\AuthenticationCookieService;
use App\Http\CsrfRequestValidator;
use App\Http\Request;
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

final class AuthenticationControllerTest extends TestCase
{
    private AuthConfig $config;
    private JwtService $jwtService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = AuthConfig::fromEnvironment();
        $this->jwtService = new JwtService($this->config);

        $now = new DateTimeImmutable();

        $this->user = new User(
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

    public function testLoginReturnsUserAndSecureCookies(): void
    {
        $authentication = $this->createMock(
            AuthenticationRepository::class
        );
        $authentication
            ->expects(self::once())
            ->method('authenticate')
            ->with('operador', 'SenhaSegura123')
            ->willReturn($this->user);

        $refreshTokens = $this->createMock(
            RefreshTokenRepository::class
        );
        $refreshTokens
            ->expects(self::once())
            ->method('register')
            ->with(
                $this->user->id,
                self::isInstanceOf(IssuedToken::class)
            );

        $response = $this->createController(
            $authentication,
            $refreshTokens
        )->login(
            $this->jsonRequest([
                'usuario' => ' OPERADOR ',
                'senha' => 'SenhaSegura123',
            ])
        );

        self::assertSame(200, $response->status());
        self::assertSame(
            [
                'user' => [
                    'id' => 10,
                    'nome' => 'Usuario Operador',
                    'email' => 'operador@example.com',
                    'usuario' => 'operador',
                    'perfil' => 'OPERADOR',
                ],
            ],
            $this->decodeBody($response->body())
        );

        $cookies = $response->headerValues('Set-Cookie');

        self::assertCount(3, $cookies);
        self::assertStringStartsWith('access_token=', $cookies[0]);
        self::assertStringStartsWith('refresh_token=', $cookies[1]);
        self::assertStringStartsWith('csrf_token=', $cookies[2]);
        self::assertStringNotContainsString(
            'SenhaSegura123',
            $response->body()
        );
        self::assertStringNotContainsString(
            'access_token',
            $response->body()
        );
        self::assertStringNotContainsString(
            'refresh_token',
            $response->body()
        );
        self::assertStringNotContainsString(
            'csrf_token',
            $response->body()
        );
    }

    public function testMeReturnsAuthenticatedUserWithoutTokens(): void
    {
        $issuedToken = $this->jwtService->issueAccessToken(
            $this->user->id,
            hash('sha256', 'csrf-token')
        );

        $response = $this->createController()->me(
            new AuthenticatedUser(
                $this->user,
                $this->jwtService->decodeAccessToken(
                    $issuedToken->value
                )
            )
        );

        self::assertSame(200, $response->status());
        self::assertSame(
            [
                'user' => [
                    'id' => 10,
                    'nome' => 'Usuario Operador',
                    'email' => 'operador@example.com',
                    'usuario' => 'operador',
                    'perfil' => 'OPERADOR',
                ],
            ],
            $this->decodeBody($response->body())
        );
        self::assertSame(
            [],
            $response->headerValues('Set-Cookie')
        );
    }

    #[DataProvider('invalidCredentials')]
    public function testRejectsInvalidLoginInput(
        array $body,
        string $invalidField
    ): void {
        $authentication = $this->createMock(
            AuthenticationRepository::class
        );
        $authentication
            ->expects(self::never())
            ->method('authenticate');

        try {
            $this->createController($authentication)
                ->login($this->jsonRequest($body));

            self::fail('Era esperada uma falha de validacao.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey(
                $invalidField,
                $exception->errors()
            );
        }
    }

    public static function invalidCredentials(): array
    {
        return [
            'missing username' => [
                ['senha' => 'SenhaSegura123'],
                'usuario',
            ],
            'non-string username' => [
                ['usuario' => 10, 'senha' => 'SenhaSegura123'],
                'usuario',
            ],
            'invalid username format' => [
                ['usuario' => 'usuario@teste', 'senha' => 'SenhaSegura123'],
                'usuario',
            ],
            'missing password' => [
                ['usuario' => 'operador'],
                'senha',
            ],
            'non-string password' => [
                ['usuario' => 'operador', 'senha' => 12345678],
                'senha',
            ],
            'password above bcrypt limit' => [
                ['usuario' => 'operador', 'senha' => str_repeat('a', 73)],
                'senha',
            ],
        ];
    }

    public function testRejectsInvalidJsonDuringLogin(): void
    {
        $authentication = $this->createMock(
            AuthenticationRepository::class
        );
        $authentication
            ->expects(self::never())
            ->method('authenticate');

        $this->expectException(
            InvalidJsonBodyException::class
        );

        $this->createController($authentication)->login(
            new Request(
                'POST',
                '/auth/login',
                body: '{json-invalido'
            )
        );
    }

    public function testRefreshRotatesTokensFromCookie(): void
    {
        $currentRefresh = $this->jwtService
            ->issueRefreshToken($this->user->id);
        $csrfToken = (new CsrfTokenService())->generate();

        $users = $this->createMock(UserRepository::class);
        $users
            ->expects(self::once())
            ->method('findById')
            ->with($this->user->id)
            ->willReturn($this->user);

        $refreshTokens = $this->createMock(
            RefreshTokenRepository::class
        );
        $refreshTokens
            ->expects(self::once())
            ->method('rotate')
            ->with(
                self::callback(
                    static fn (TokenClaims $claims): bool =>
                        $claims->jti === $currentRefresh->jti
                ),
                self::isInstanceOf(IssuedToken::class)
            );

        $response = $this->createController(
            refreshTokens: $refreshTokens,
            users: $users
        )->refresh(
            new Request(
                'POST',
                '/auth/refresh',
                headers: [
                    'x-csrf-token' => $csrfToken,
                ],
                cookies: [
                    AuthenticationCookieService::REFRESH_COOKIE
                        => $currentRefresh->value,
                    AuthenticationCookieService::CSRF_COOKIE
                        => $csrfToken,
                ]
            )
        );

        self::assertSame(200, $response->status());
        self::assertCount(
            3,
            $response->headerValues('Set-Cookie')
        );
        self::assertSame(
            10,
            $this->decodeBody($response->body())['user']['id']
        );
    }

    public function testRejectsRefreshWithoutCookie(): void
    {
        $csrfToken = (new CsrfTokenService())->generate();

        $this->expectException(InvalidTokenException::class);

        $this->createController()->refresh(
            new Request(
                'POST',
                '/auth/refresh',
                headers: [
                    'x-csrf-token' => $csrfToken,
                ],
                cookies: [
                    AuthenticationCookieService::CSRF_COOKIE
                        => $csrfToken,
                ]
            )
        );
    }

    public function testRejectsRefreshWithMismatchedCsrf(): void
    {
        $this->expectException(
            InvalidCsrfTokenException::class
        );

        $this->createController()->refresh(
            new Request(
                'POST',
                '/auth/refresh',
                headers: [
                    'x-csrf-token' => str_repeat('a', 64),
                ],
                cookies: [
                    AuthenticationCookieService::CSRF_COOKIE
                        => str_repeat('b', 64),
                ]
            )
        );
    }

    public function testLogoutRevokesSessionAndClearsCookies(): void
    {
        $accessToken = $this->jwtService->issueAccessToken(
            $this->user->id,
            hash('sha256', 'csrf-de-logout')
        );
        $refreshToken = $this->jwtService
            ->issueRefreshToken($this->user->id);
        $csrfToken = (new CsrfTokenService())->generate();

        $refreshTokens = $this->createMock(
            RefreshTokenRepository::class
        );
        $refreshTokens
            ->expects(self::once())
            ->method('revokeFamily')
            ->with($this->user->id, $refreshToken->familyId);

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

        $response = $this->createController(
            refreshTokens: $refreshTokens,
            blacklist: $blacklist
        )->logout(
            new Request(
                'POST',
                '/auth/logout',
                headers: [
                    'x-csrf-token' => $csrfToken,
                ],
                cookies: [
                    AuthenticationCookieService::ACCESS_COOKIE
                        => $accessToken->value,
                    AuthenticationCookieService::REFRESH_COOKIE
                        => $refreshToken->value,
                    AuthenticationCookieService::CSRF_COOKIE
                        => $csrfToken,
                ]
            )
        );

        self::assertSame(204, $response->status());
        self::assertSame('', $response->body());

        $cookies = $response->headerValues('Set-Cookie');

        self::assertCount(3, $cookies);

        foreach ($cookies as $cookie) {
            self::assertStringContainsString(
                'Max-Age=0',
                $cookie
            );
        }
    }

    public function testRejectsLogoutWithoutCsrf(): void
    {
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

        $this->expectException(
            InvalidCsrfTokenException::class
        );

        $this->createController(
            refreshTokens: $refreshTokens,
            blacklist: $blacklist
        )->logout(
            new Request('POST', '/auth/logout')
        );
    }

    private function createController(
        ?AuthenticationRepository $authentication = null,
        ?RefreshTokenRepository $refreshTokens = null,
        ?UserRepository $users = null,
        ?TokenBlacklistRepository $blacklist = null
    ): AuthenticationController {
        $service = new AuthenticationService(
            $authentication ?? $this->createMock(
                AuthenticationRepository::class
            ),
            $this->jwtService,
            new CsrfTokenService(),
            $refreshTokens ?? $this->createMock(
                RefreshTokenRepository::class
            ),
            $users ?? $this->createMock(
                UserRepository::class
            ),
            $blacklist ?? $this->createMock(
                TokenBlacklistRepository::class
            )
        );

        return new AuthenticationController(
            $service,
            new AuthenticationCookieService($this->config),
            new CsrfRequestValidator(
                $this->config,
                new CsrfTokenService()
            )
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    private function jsonRequest(array $body): Request
    {
        return new Request(
            'POST',
            '/auth/login',
            body: json_encode(
                $body,
                JSON_THROW_ON_ERROR
            )
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBody(string $body): array
    {
        return json_decode(
            $body,
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }
}
