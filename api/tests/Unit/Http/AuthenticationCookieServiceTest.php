<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Config\AuthConfig;
use App\Dto\AuthenticationResult;
use App\Dto\IssuedToken;
use App\Entity\TokenType;
use App\Entity\User;
use App\Entity\UserProfile;
use App\Http\AuthenticationCookieService;
use App\Http\Response;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AuthenticationCookieServiceTest extends TestCase
{
    private const VARIABLES = [
        'AUTH_COOKIE_SECURE',
        'AUTH_COOKIE_SAME_SITE',
        'AUTH_CSRF_ENABLED',
    ];

    /**
     * @var array<string, string|false>
     */
    private array $originalValues = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::VARIABLES as $name) {
            $this->originalValues[$name] = getenv($name);
        }

        $this->setEnvironment([
            'AUTH_COOKIE_SECURE' => 'false',
            'AUTH_COOKIE_SAME_SITE' => 'Lax',
            'AUTH_CSRF_ENABLED' => 'true',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->originalValues as $name => $value) {
            if ($value === false) {
                putenv($name);

                continue;
            }

            putenv($name . '=' . $value);
        }

        parent::tearDown();
    }

    public function testAddsAuthenticationCookies(): void
    {
        $response = $this->createService()
            ->addAuthenticationCookies(
                Response::json(['authenticated' => true]),
                $this->createAuthenticationResult()
            );

        $cookies = $response->headerValues('Set-Cookie');

        self::assertCount(3, $cookies);

        self::assertStringContainsString(
            'access_token=access.jwt; Path=/;',
            $cookies[0]
        );
        self::assertStringContainsString(
            'Max-Age=900; SameSite=Lax; HttpOnly',
            $cookies[0]
        );

        self::assertStringContainsString(
            'refresh_token=refresh.jwt; Path=/auth;',
            $cookies[1]
        );
        self::assertStringContainsString(
            'Max-Age=86400; SameSite=Lax; HttpOnly',
            $cookies[1]
        );

        self::assertStringContainsString(
            'csrf_token=csrf-token; Path=/;',
            $cookies[2]
        );
        self::assertStringContainsString(
            'Max-Age=900; SameSite=Lax',
            $cookies[2]
        );
        self::assertStringNotContainsString(
            'HttpOnly',
            $cookies[2]
        );
    }

    public function testAddsSecureAttributeWhenEnabled(): void
    {
        $this->setEnvironment([
            'AUTH_COOKIE_SECURE' => 'true',
        ]);

        $cookies = $this->createService()
            ->addAuthenticationCookies(
                Response::empty(),
                $this->createAuthenticationResult()
            )
            ->headerValues('Set-Cookie');

        foreach ($cookies as $cookie) {
            self::assertStringContainsString(
                '; Secure',
                $cookie
            );
        }
    }

    public function testDoesNotAddCsrfCookieWhenDisabled(): void
    {
        $this->setEnvironment([
            'AUTH_CSRF_ENABLED' => 'false',
        ]);

        $cookies = $this->createService()
            ->addAuthenticationCookies(
                Response::empty(),
                $this->createAuthenticationResult()
            )
            ->headerValues('Set-Cookie');

        self::assertCount(2, $cookies);
        self::assertStringStartsWith(
            'access_token=',
            $cookies[0]
        );
        self::assertStringStartsWith(
            'refresh_token=',
            $cookies[1]
        );
    }

    public function testClearsAuthenticationCookies(): void
    {
        $cookies = $this->createService()
            ->clearAuthenticationCookies(
                Response::empty()
            )
            ->headerValues('Set-Cookie');

        self::assertCount(3, $cookies);

        foreach ($cookies as $cookie) {
            self::assertStringContainsString(
                'Expires=Thu, 01 Jan 1970 00:00:01 GMT',
                $cookie
            );
            self::assertStringContainsString(
                'Max-Age=0',
                $cookie
            );
        }

        self::assertStringContainsString(
            'access_token=; Path=/',
            $cookies[0]
        );
        self::assertStringContainsString(
            'refresh_token=; Path=/auth',
            $cookies[1]
        );
        self::assertStringContainsString(
            'csrf_token=; Path=/',
            $cookies[2]
        );
    }

    public function testEncodesCookieValues(): void
    {
        $maliciousValue = "token; Domain=evil.test\r\nX-Evil: yes";

        $cookies = $this->createService()
            ->addAuthenticationCookies(
                Response::empty(),
                $this->createAuthenticationResult(
                    accessValue: $maliciousValue,
                    refreshValue: $maliciousValue,
                    csrfToken: $maliciousValue
                )
            )
            ->headerValues('Set-Cookie');

        foreach ($cookies as $cookie) {
            self::assertStringContainsString(
                rawurlencode($maliciousValue),
                $cookie
            );
            self::assertStringNotContainsString("\r", $cookie);
            self::assertStringNotContainsString("\n", $cookie);
            self::assertStringNotContainsString(
                'Domain=evil.test',
                $cookie
            );
        }
    }

    public function testCreatesHostOnlyCookies(): void
    {
        $cookies = $this->createService()
            ->addAuthenticationCookies(
                Response::empty(),
                $this->createAuthenticationResult()
            )
            ->headerValues('Set-Cookie');

        foreach ($cookies as $cookie) {
            self::assertStringNotContainsString(
                'Domain=',
                $cookie
            );
        }
    }

    public function testUsesTokenExpirationInCookies(): void
    {
        $cookies = $this->createService()
            ->addAuthenticationCookies(
                Response::empty(),
                $this->createAuthenticationResult()
            )
            ->headerValues('Set-Cookie');

        self::assertStringContainsString(
            'Expires=' . gmdate(
                DATE_RFC7231,
                1_700_000_000 + 900
            ),
            $cookies[0]
        );
        self::assertStringContainsString(
            'Expires=' . gmdate(
                DATE_RFC7231,
                1_700_000_000 + 86400
            ),
            $cookies[1]
        );
        self::assertStringContainsString(
            'Expires=' . gmdate(
                DATE_RFC7231,
                1_700_000_000 + 900
            ),
            $cookies[2]
        );
    }

    public function testSupportsStrictSameSitePolicy(): void
    {
        $this->setEnvironment([
            'AUTH_COOKIE_SAME_SITE' => 'Strict',
        ]);

        $cookies = $this->createService()
            ->addAuthenticationCookies(
                Response::empty(),
                $this->createAuthenticationResult()
            )
            ->headerValues('Set-Cookie');

        foreach ($cookies as $cookie) {
            self::assertStringContainsString(
                'SameSite=Strict',
                $cookie
            );
        }
    }

    public function testPreservesOriginalResponse(): void
    {
        $original = Response::json(
            ['authenticated' => true],
            201
        )->withHeader('X-Request-Id', 'request-123');

        $response = $this->createService()
            ->addAuthenticationCookies(
                $original,
                $this->createAuthenticationResult()
            );

        self::assertSame(201, $response->status());
        self::assertSame($original->body(), $response->body());
        self::assertSame(
            ['request-123'],
            $response->headerValues('X-Request-Id')
        );
        self::assertSame(
            [],
            $original->headerValues('Set-Cookie')
        );
    }

    public function testClearsCookiesWithSecurityAttributes(): void
    {
        $this->setEnvironment([
            'AUTH_COOKIE_SECURE' => 'true',
            'AUTH_COOKIE_SAME_SITE' => 'Strict',
        ]);

        $cookies = $this->createService()
            ->clearAuthenticationCookies(Response::empty())
            ->headerValues('Set-Cookie');

        foreach ($cookies as $cookie) {
            self::assertStringContainsString('; Secure', $cookie);
            self::assertStringContainsString(
                'SameSite=Strict',
                $cookie
            );
        }

        self::assertStringContainsString('HttpOnly', $cookies[0]);
        self::assertStringContainsString('HttpOnly', $cookies[1]);
        self::assertStringNotContainsString(
            'HttpOnly',
            $cookies[2]
        );
    }

    private function createService(): AuthenticationCookieService
    {
        return new AuthenticationCookieService(
            AuthConfig::fromEnvironment()
        );
    }

    private function createAuthenticationResult(
        string $accessValue = 'access.jwt',
        string $refreshValue = 'refresh.jwt',
        string $csrfToken = 'csrf-token'
    ): AuthenticationResult {
        $issuedAt = 1_700_000_000;

        return new AuthenticationResult(
            user: new User(
                id: 10,
                name: 'Usuario Teste',
                email: 'usuario@example.com',
                username: 'usuario',
                profile: UserProfile::Operator,
                active: true,
                createdAt: new DateTimeImmutable(
                    '@' . $issuedAt
                ),
                updatedAt: new DateTimeImmutable(
                    '@' . $issuedAt
                )
            ),
            accessToken: new IssuedToken(
                value: $accessValue,
                jti: str_repeat('a', 64),
                type: TokenType::Access,
                issuedAt: $issuedAt,
                expiresAt: $issuedAt + 900
            ),
            refreshToken: new IssuedToken(
                value: $refreshValue,
                jti: str_repeat('b', 64),
                type: TokenType::Refresh,
                issuedAt: $issuedAt,
                expiresAt: $issuedAt + 86400,
                familyId: str_repeat('c', 64)
            ),
            csrfToken: $csrfToken
        );
    }

    /**
     * @param array<string, string> $values
     */
    private function setEnvironment(array $values): void
    {
        foreach ($values as $name => $value) {
            putenv($name . '=' . $value);
        }
    }
}
