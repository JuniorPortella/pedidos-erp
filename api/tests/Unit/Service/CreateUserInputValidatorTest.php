<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\UserProfile;
use App\Exception\ValidationException;
use App\Service\CreateUserInputValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CreateUserInputValidatorTest extends TestCase
{
    private CreateUserInputValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new CreateUserInputValidator();
    }

    public function testNormalizesValidInput(): void
    {
        $input = $this->validator->validate([
            'nome' => '  Vagner Portella  ',
            'email' => '  VAGNER@EXAMPLE.COM  ',
            'usuario' => '  Vagner.Portella  ',
            'senha' => ' Senha@123 ',
            'perfil' => ' admin ',
        ]);

        self::assertSame('Vagner Portella', $input->name);
        self::assertSame('vagner@example.com', $input->email);
        self::assertSame('vagner.portella', $input->username);
        self::assertSame(' Senha@123 ', $input->password);
        self::assertSame(UserProfile::Admin, $input->profile);
    }

    public function testUsesOperatorAsDefaultProfile(): void
    {
        $data = self::validInput();
        unset($data['perfil']);

        $input = $this->validator->validate($data);

        self::assertSame(
            UserProfile::Operator,
            $input->profile
        );
    }

    public function testReturnsAllRequiredFieldErrors(): void
    {
        try {
            $this->validator->validate([]);

            self::fail(
                'Era esperada uma ValidationException.'
            );
        } catch (ValidationException $exception) {
            self::assertSame(
                'Dados invalidos.',
                $exception->getMessage()
            );

            self::assertSame(
                [
                    'nome' => 'Informe o nome.',
                    'email' => 'Informe o e-mail.',
                    'usuario' => 'Informe o usuario.',
                    'senha' => 'Informe a senha.',
                ],
                $exception->errors()
            );
        }
    }

    /**
     * @param array<string, mixed> $changes
     */
    #[DataProvider('invalidFields')]
    public function testRejectsInvalidField(
        array $changes,
        string $field,
        string $message
    ): void {
        $data = array_replace(
            self::validInput(),
            $changes
        );

        try {
            $this->validator->validate($data);

            self::fail(
                'Era esperada uma ValidationException.'
            );
        } catch (ValidationException $exception) {
            self::assertSame(
                $message,
                $exception->errors()[$field] ?? null
            );
        }
    }

    public static function invalidFields(): array
    {
        return [
            'name too long' => [
                ['nome' => str_repeat('a', 121)],
                'nome',
                'O nome deve possuir no maximo 120 caracteres.',
            ],
            'invalid email' => [
                ['email' => 'email-invalido'],
                'email',
                'Informe um e-mail valido.',
            ],
            'invalid username' => [
                ['usuario' => 'usuario com espaco'],
                'usuario',
                'O usuario deve possuir entre 3 e 60 caracteres e usar apenas letras, numeros, ponto, hifen ou sublinhado.',
            ],
            'short password' => [
                ['senha' => 'Aa1@5678'],
                'senha',
                'A senha deve possuir mais de 8 caracteres.',
            ],
            'password too long' => [
                ['senha' => str_repeat('a', 73)],
                'senha',
                'A senha deve possuir no maximo 72 bytes.',
            ],
            'missing uppercase letter' => [
                ['senha' => 'senha@123'],
                'senha',
                'A senha deve conter uma letra maiuscula, uma minuscula, um numero e um caractere especial.',
            ],
            'missing lowercase letter' => [
                ['senha' => 'SENHA@123'],
                'senha',
                'A senha deve conter uma letra maiuscula, uma minuscula, um numero e um caractere especial.',
            ],
            'missing number' => [
                ['senha' => 'Senha@Teste'],
                'senha',
                'A senha deve conter uma letra maiuscula, uma minuscula, um numero e um caractere especial.',
            ],
            'missing special character' => [
                ['senha' => 'Senha12345'],
                'senha',
                'A senha deve conter uma letra maiuscula, uma minuscula, um numero e um caractere especial.',
            ],
            'space is not a special character' => [
                ['senha' => 'Senha 123'],
                'senha',
                'A senha deve conter uma letra maiuscula, uma minuscula, um numero e um caractere especial.',
            ],
            'invalid profile' => [
                ['perfil' => 'GERENTE'],
                'perfil',
                'O perfil deve ser ADMIN ou OPERADOR.',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function validInput(): array
    {
        return [
            'nome' => 'Vagner Portella',
            'email' => 'vagner@example.com',
            'usuario' => 'vagner',
            'senha' => 'Senha@123',
            'perfil' => 'OPERADOR',
        ];
    }
}
