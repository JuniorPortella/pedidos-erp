<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\PasswordPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    #[DataProvider('validPasswords')]
    public function testAcceptsStrongPassword(string $password): void
    {
        self::assertNull(
            (new PasswordPolicy())->validationError($password)
        );
    }

    public static function validPasswords(): array
    {
        return [
            'ascii punctuation' => ['SenhaSegura@123'],
            'unicode letters and symbol' => ['SenhaForteÇ#9'],
        ];
    }

    #[DataProvider('invalidPasswords')]
    public function testRejectsWeakPassword(
        string $password,
        string $message
    ): void {
        self::assertSame(
            $message,
            (new PasswordPolicy())->validationError($password)
        );
    }

    public static function invalidPasswords(): array
    {
        $complexityMessage =
            'A senha deve conter uma letra maiuscula, uma minuscula, um numero e um caractere especial.';

        return [
            'eight characters' => [
                'Aa1@5678',
                'A senha deve possuir mais de 8 caracteres.',
            ],
            'above bcrypt limit' => [
                str_repeat('A', 68) . 'a1@xy',
                'A senha deve possuir no maximo 72 bytes.',
            ],
            'without uppercase' => ['senha@123', $complexityMessage],
            'without lowercase' => ['SENHA@123', $complexityMessage],
            'without number' => ['Senha@Teste', $complexityMessage],
            'without special' => ['Senha12345', $complexityMessage],
            'space is not special' => ['Senha 123', $complexityMessage],
        ];
    }
}
