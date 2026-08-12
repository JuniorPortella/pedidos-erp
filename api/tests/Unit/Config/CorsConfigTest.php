<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\CorsConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CorsConfigTest extends TestCase
{
    private string|false $originalEnvironment;
    private string|false $originalOrigin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalEnvironment = getenv('APP_ENV');
        $this->originalOrigin = getenv('FRONTEND_ORIGIN');

        putenv('APP_ENV=development');
        putenv('FRONTEND_ORIGIN=http://localhost:5173');
    }

    protected function tearDown(): void
    {
        $this->restore(
            'APP_ENV',
            $this->originalEnvironment
        );
        $this->restore(
            'FRONTEND_ORIGIN',
            $this->originalOrigin
        );

        parent::tearDown();
    }

    public function testLoadsAllowedOrigin(): void
    {
        $config = CorsConfig::fromEnvironment();

        self::assertSame(
            'http://localhost:5173',
            $config->allowedOrigin
        );
    }

    #[DataProvider('invalidOrigins')]
    public function testRejectsInvalidOrigin(
        string $origin
    ): void {
        putenv('FRONTEND_ORIGIN=' . $origin);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'FRONTEND_ORIGIN deve conter uma origem valida.'
        );

        CorsConfig::fromEnvironment();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidOrigins(): array
    {
        return [
            'wildcard' => ['*'],
            'path' => ['http://localhost:5173/app'],
            'query' => ['http://localhost:5173?teste=1'],
            'credentials' => [
                'http://usuario@localhost:5173',
            ],
            'unsupported scheme' => [
                'ftp://localhost:5173',
            ],
            'surrounding whitespace' => [
                ' http://localhost:5173 ',
            ],
        ];
    }

    public function testRequiresHttpsInProduction(): void
    {
        putenv('APP_ENV=production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'FRONTEND_ORIGIN deve usar HTTPS em producao.'
        );

        CorsConfig::fromEnvironment();
    }

    private function restore(
        string $name,
        string|false $value
    ): void {
        if ($value === false) {
            putenv($name);

            return;
        }

        putenv($name . '=' . $value);
    }
}
