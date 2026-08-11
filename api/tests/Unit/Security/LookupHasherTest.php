<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\LookupHasher;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LookupHasherTest extends TestCase
{
    private const CONTEXT = 'usuarios.email';

    private LookupHasher $hasher;

    protected function setUp(): void
    {
        parent::setUp();

        $key = random_bytes(32);

        $this->hasher = new LookupHasher(
            base64_encode($key)
        );
    }

    public function testProducesSameHashForSameValue(): void
    {
        $first = $this->hasher->hash(
            'usuario@example.com',
            self::CONTEXT
        );

        $second = $this->hasher->hash(
            'usuario@example.com',
            self::CONTEXT
        );

        self::assertSame($first, $second);
        self::assertMatchesRegularExpression(
            '/\A[a-f0-9]{64}\z/',
            $first
        );
    }

    public function testProducesDifferentHashForDifferentValue(): void
    {
        $first = $this->hasher->hash(
            'primeiro@example.com',
            self::CONTEXT
        );

        $second = $this->hasher->hash(
            'segundo@example.com',
            self::CONTEXT
        );

        self::assertNotSame($first, $second);
    }

    public function testProducesDifferentHashForDifferentContext(): void
    {
        $emailHash = $this->hasher->hash(
            'usuario@example.com',
            'usuarios.email'
        );

        $nameHash = $this->hasher->hash(
            'usuario@example.com',
            'usuarios.nome'
        );

        self::assertNotSame($emailHash, $nameHash);
    }

    #[DataProvider('invalidKeys')]
    public function testRejectsInvalidKey(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Chave de consulta invalida.'
        );

        new LookupHasher($key);
    }

    public static function invalidKeys(): array
    {
        return [
            'invalid base64' => ['***'],
            'invalid length' => [base64_encode('curta')],
        ];
    }

    public function testRejectsEmptyContext(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Contexto de consulta obrigatorio.'
        );

        $this->hasher->hash(
            'usuario@example.com',
            ''
        );
    }
}
