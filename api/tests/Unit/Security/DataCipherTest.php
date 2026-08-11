<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\DataCipher;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DataCipherTest extends TestCase
{
    private const CONTEXT = 'usuarios.email';

    private DataCipher $cipher;

    protected function setUp(): void
    {
        parent::setUp();

        $key = random_bytes(
            SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES
        );

        $this->cipher = new DataCipher(base64_encode($key));
    }

    public function testEncryptsAndDecryptsValue(): void
    {
        $plaintext = 'usuario@example.com';

        $encrypted = $this->cipher->encrypt(
            $plaintext,
            self::CONTEXT
        );

        self::assertNotSame($plaintext, $encrypted);

        self::assertSame(
            $plaintext,
            $this->cipher->decrypt($encrypted, self::CONTEXT)
        );
    }

    public function testProducesDifferentPayloadsForSameValue(): void
    {
        $first = $this->cipher->encrypt(
            'usuario@example.com',
            self::CONTEXT
        );

        $second = $this->cipher->encrypt(
            'usuario@example.com',
            self::CONTEXT
        );

        self::assertNotSame($first, $second);
    }

    #[DataProvider('invalidKeys')]
    public function testRejectsInvalidKey(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Chave de criptografia invalida.'
        );

        new DataCipher($key);
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
            'Contexto de criptografia obrigatorio.'
        );

        $this->cipher->encrypt('conteudo', '');
    }

    public function testRejectsTamperedPayload(): void
    {
        $encrypted = $this->cipher->encrypt(
            'usuario@example.com',
            self::CONTEXT
        );

        $payload = base64_decode($encrypted, true);

        self::assertIsString($payload);

        $lastIndex = strlen($payload) - 1;
        $payload[$lastIndex] = chr(ord($payload[$lastIndex]) ^ 1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Nao foi possivel descriptografar o conteudo.'
        );

        $this->cipher->decrypt(
            base64_encode($payload),
            self::CONTEXT
        );
    }

    public function testRejectsDifferentContext(): void
    {
        $encrypted = $this->cipher->encrypt(
            'usuario@example.com',
            self::CONTEXT
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Nao foi possivel descriptografar o conteudo.'
        );

        $this->cipher->decrypt(
            $encrypted,
            'usuarios.nome'
        );
    }
}
