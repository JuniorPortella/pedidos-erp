<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Config\AuthConfig;
use App\Exception\InvalidCsrfTokenException;
use App\Http\AuthenticationCookieService;
use App\Http\CsrfRequestValidator;
use App\Http\Request;
use App\Security\CsrfTokenService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CsrfRequestValidatorTest extends TestCase
{
    private string|false $originalCsrfEnabled;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalCsrfEnabled = getenv(
            'AUTH_CSRF_ENABLED'
        );

        putenv('AUTH_CSRF_ENABLED=true');
    }

    protected function tearDown(): void
    {
        if ($this->originalCsrfEnabled === false) {
            putenv('AUTH_CSRF_ENABLED');
        } else {
            putenv(
                'AUTH_CSRF_ENABLED='
                . $this->originalCsrfEnabled
            );
        }

        parent::tearDown();
    }

    public function testAcceptsMatchingCookieAndHeader(): void
    {
        $token = (new CsrfTokenService())->generate();

        $this->createValidator()->validate(
            $this->request($token, $token)
        );

        self::assertTrue(true);
    }

    #[DataProvider('invalidTokens')]
    public function testRejectsInvalidTokens(
        ?string $cookie,
        ?string $header
    ): void {
        $this->expectException(
            InvalidCsrfTokenException::class
        );
        $this->expectExceptionMessage(
            'Token CSRF invalido.'
        );

        $this->createValidator()->validate(
            $this->request($cookie, $header)
        );
    }

    public static function invalidTokens(): array
    {
        return [
            'missing cookie' => [
                null,
                str_repeat('a', 64),
            ],
            'missing header' => [
                str_repeat('a', 64),
                null,
            ],
            'different values' => [
                str_repeat('a', 64),
                str_repeat('b', 64),
            ],
            'malformed cookie' => [
                'token-invalido',
                'token-invalido',
            ],
            'malformed header' => [
                str_repeat('a', 64),
                'token-invalido',
            ],
        ];
    }

    public function testAllowsRequestWhenCsrfIsDisabled(): void
    {
        putenv('AUTH_CSRF_ENABLED=false');

        $this->createValidator()->validate(
            new Request('POST', '/auth/logout')
        );

        self::assertTrue(true);
    }

    private function createValidator(): CsrfRequestValidator
    {
        return new CsrfRequestValidator(
            AuthConfig::fromEnvironment(),
            new CsrfTokenService()
        );
    }

    private function request(
        ?string $cookie,
        ?string $header
    ): Request {
        return new Request(
            'POST',
            '/auth/logout',
            headers: $header === null
                ? []
                : ['x-csrf-token' => $header],
            cookies: $cookie === null
                ? []
                : [
                    AuthenticationCookieService::CSRF_COOKIE
                        => $cookie,
                ]
        );
    }
}
