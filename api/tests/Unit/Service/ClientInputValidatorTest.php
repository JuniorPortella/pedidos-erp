<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Exception\ValidationException;
use App\Service\ClientInputValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClientInputValidatorTest extends TestCase
{
    private ClientInputValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ClientInputValidator();
    }

    public function testNormalizesValidInput(): void
    {
        $input = $this->validator->validate([
            'nome' => '  Cliente Teste  ',
            'telefone' => '  +55 (11) 99999-9999  ',
        ]);

        self::assertSame('Cliente Teste', $input->name);
        self::assertSame('+55 (11) 99999-9999', $input->phone);
    }

    public function testRejectsMissingFields(): void
    {
        try {
            $this->validator->validate([]);
            self::fail('Era esperada uma ValidationException.');
        } catch (ValidationException $exception) {
            self::assertSame(
                ['nome', 'telefone'],
                array_keys($exception->errors())
            );
        }
    }

    #[DataProvider('invalidPhones')]
    public function testRejectsInvalidPhone(string $phone): void
    {
        try {
            $this->validator->validate([
                'nome' => 'Cliente',
                'telefone' => $phone,
            ]);
            self::fail('Era esperada uma ValidationException.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey(
                'telefone',
                $exception->errors()
            );
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPhones(): array
    {
        return [
            'letters' => ['11 ABCD-9999'],
            'too short' => ['1234567'],
            'too many digits' => ['1234567890123456'],
            'too long' => ['+55 (11) 99999-9999 00'],
        ];
    }

    public function testRejectsNameOverMaximumLength(): void
    {
        try {
            $this->validator->validate([
                'nome' => str_repeat('a', 121),
                'telefone' => '11999999999',
            ]);
            self::fail('Era esperada uma ValidationException.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('nome', $exception->errors());
        }
    }
}
