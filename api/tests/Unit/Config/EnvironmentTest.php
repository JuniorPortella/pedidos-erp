<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\Environment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EnvironmentTest extends TestCase
{
    private const VARIABLE = 'PEDIDOS_TEST_ENVIRONMENT';

    protected function tearDown(): void
    {
        putenv(self::VARIABLE);

        parent::tearDown();
    }

    public function testReturnsRequiredValue(): void
    {
        putenv(self::VARIABLE . '=mysql');

        self::assertSame(
            'mysql',
            Environment::getRequired(self::VARIABLE)
        );
    }

    public function testThrowsExceptionWhenRequiredValueIsMissing(): void
    {
        putenv(self::VARIABLE);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Variavel de ambiente obrigatoria nao configurada: '
            . self::VARIABLE
        );

        Environment::getRequired(self::VARIABLE);
    }

    #[DataProvider('booleanValues')]
    public function testConvertsBooleanValue(
        string $rawValue,
        bool $expected
    ): void {
        putenv(self::VARIABLE . '=' . $rawValue);

        self::assertSame(
            $expected,
            Environment::getBoolean(self::VARIABLE)
        );
    }

    public static function booleanValues(): array
    {
        return [
            'true' => ['true', true],
            'false' => ['false', false],
            'one' => ['1', true],
            'zero' => ['0', false],
        ];
    }

    public function testThrowsExceptionWhenBooleanIsInvalid(): void
    {
        putenv(self::VARIABLE . '=talvez');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Variavel de ambiente booleana invalida: '
            . self::VARIABLE
        );

        Environment::getBoolean(self::VARIABLE);
    }

    #[DataProvider('positiveIntegerValues')]
    public function testConvertsPositiveInteger(
        string $rawValue,
        int $expected
    ): void {
        putenv(self::VARIABLE . '=' . $rawValue);

        self::assertSame(
            $expected,
            Environment::getPositiveInteger(
                self::VARIABLE
            )
        );
    }

    public static function positiveIntegerValues(): array
    {
        return [
            'one' => ['1', 1],
            'access ttl' => ['900', 900],
            'refresh ttl' => ['86400', 86400],
        ];
    }

    #[DataProvider('invalidPositiveIntegerValues')]
    public function testRejectsInvalidPositiveInteger(
        string $rawValue
    ): void {
        putenv(self::VARIABLE . '=' . $rawValue);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Variavel de ambiente deve ser um inteiro positivo: '
            . self::VARIABLE
        );

        Environment::getPositiveInteger(
            self::VARIABLE
        );
    }

    public static function invalidPositiveIntegerValues(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-1'],
            'decimal' => ['1.5'],
            'text' => ['texto'],
        ];
    }
}
