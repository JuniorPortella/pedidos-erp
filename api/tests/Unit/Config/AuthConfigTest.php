<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\AuthConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AuthConfigTest extends TestCase
{
    private const VARIABLES = [
        'APP_ENV',
        'APP_DEBUG',
        'JWT_ACCESS_SECRET',
        'JWT_REFRESH_SECRET',
        'JWT_ACCESS_TTL',
        'JWT_REFRESH_TTL',
        'JWT_ISSUER',
        'JWT_AUDIENCE',
        'AUTH_COOKIE_SECURE',
        'AUTH_COOKIE_SAME_SITE',
        'AUTH_CSRF_ENABLED',
        'AUTH_LOGIN_MAX_ATTEMPTS',
        'AUTH_LOGIN_IP_MAX_ATTEMPTS',
        'AUTH_LOGIN_WINDOW',
        'AUTH_LOGIN_BLOCK',
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
            'APP_ENV' => 'development',
            'APP_DEBUG' => 'true',
            'JWT_ACCESS_SECRET' => base64_encode(
                random_bytes(32)
            ),
            'JWT_REFRESH_SECRET' => base64_encode(
                random_bytes(32)
            ),
            'JWT_ACCESS_TTL' => '900',
            'JWT_REFRESH_TTL' => '86400',
            'JWT_ISSUER' => 'pedidos-api',
            'JWT_AUDIENCE' => 'pedidos-frontend',
            'AUTH_COOKIE_SECURE' => 'false',
            'AUTH_COOKIE_SAME_SITE' => 'Lax',
            'AUTH_CSRF_ENABLED' => 'true',
            'AUTH_LOGIN_MAX_ATTEMPTS' => '6',
            'AUTH_LOGIN_IP_MAX_ATTEMPTS' => '24',
            'AUTH_LOGIN_WINDOW' => '600',
            'AUTH_LOGIN_BLOCK' => '1200',
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

    public function testLoadsDevelopmentConfiguration(): void
    {
        $config = AuthConfig::fromEnvironment();

        self::assertSame(32, strlen($config->accessSecret));
        self::assertSame(32, strlen($config->refreshSecret));
        self::assertNotSame(
            $config->accessSecret,
            $config->refreshSecret
        );
        self::assertSame(900, $config->accessTtl);
        self::assertSame(86400, $config->refreshTtl);
        self::assertSame('pedidos-api', $config->issuer);
        self::assertSame(
            'pedidos-frontend',
            $config->audience
        );
        self::assertFalse($config->cookieSecure);
        self::assertSame('Lax', $config->cookieSameSite);
        self::assertTrue($config->csrfEnabled);
        self::assertSame(6, $config->loginMaxAttempts);
        self::assertSame(24, $config->loginIpMaxAttempts);
        self::assertSame(600, $config->loginWindowSeconds);
        self::assertSame(1200, $config->loginBlockSeconds);
    }

    public function testLoadsSecureProductionConfiguration(): void
    {
        $this->configureProduction();

        $config = AuthConfig::fromEnvironment();

        self::assertTrue($config->cookieSecure);
    }

    #[DataProvider('insecureProductionSettings')]
    public function testRejectsInsecureProductionConfiguration(
        string $name,
        string $value,
        string $message
    ): void {
        $this->configureProduction();
        $this->setEnvironment([$name => $value]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        AuthConfig::fromEnvironment();
    }

    public static function insecureProductionSettings(): array
    {
        return [
            'debug enabled' => [
                'APP_DEBUG',
                'true',
                'APP_DEBUG deve ser false em producao.',
            ],
            'insecure cookie' => [
                'AUTH_COOKIE_SECURE',
                'false',
                'Cookies de autenticacao devem ser Secure em producao.',
            ],
            'csrf disabled' => [
                'AUTH_CSRF_ENABLED',
                'false',
                'A protecao CSRF deve estar ativa em producao.',
            ],
        ];
    }

    public function testRejectsEqualSecrets(): void
    {
        $secret = (string) getenv(
            'JWT_ACCESS_SECRET'
        );

        $this->setEnvironment([
            'JWT_REFRESH_SECRET' => $secret,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'As chaves de access e refresh devem ser diferentes.'
        );

        AuthConfig::fromEnvironment();
    }

    public function testRejectsAccessTtlGreaterThanRefreshTtl(): void
    {
        $this->setEnvironment([
            'JWT_ACCESS_TTL' => '86400',
            'JWT_REFRESH_TTL' => '900',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'O TTL do access token deve ser menor que o TTL do refresh token.'
        );

        AuthConfig::fromEnvironment();
    }

    #[DataProvider('invalidSameSiteSettings')]
    public function testRejectsInvalidSameSiteConfiguration(
        string $sameSite,
        string $secure,
        string $message
    ): void {
        $this->setEnvironment([
            'AUTH_COOKIE_SAME_SITE' => $sameSite,
            'AUTH_COOKIE_SECURE' => $secure,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        AuthConfig::fromEnvironment();
    }

    public static function invalidSameSiteSettings(): array
    {
        return [
            'invalid value' => [
                'Invalido',
                'true',
                'AUTH_COOKIE_SAME_SITE deve ser Lax, Strict ou None.',
            ],
            'none without secure' => [
                'None',
                'false',
                'SameSite=None exige cookies Secure.',
            ],
        ];
    }

    #[DataProvider('invalidSecrets')]
    public function testRejectsInvalidSecret(
        string $secret
    ): void {
        $this->setEnvironment([
            'JWT_ACCESS_SECRET' => $secret,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'A chave JWT_ACCESS_SECRET deve possuir pelo menos 32 bytes em Base64.'
        );

        AuthConfig::fromEnvironment();
    }

    public static function invalidSecrets(): array
    {
        return [
            'invalid base64' => ['***'],
            'short secret' => [
                base64_encode('chave-curta'),
            ],
        ];
    }

    private function configureProduction(): void
    {
        $this->setEnvironment([
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'AUTH_COOKIE_SECURE' => 'true',
            'AUTH_CSRF_ENABLED' => 'true',
        ]);
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
