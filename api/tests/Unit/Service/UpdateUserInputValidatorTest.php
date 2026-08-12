<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\UserProfile;
use App\Exception\ValidationException;
use App\Service\PasswordPolicy;
use App\Service\UpdateUserInputValidator;
use PHPUnit\Framework\TestCase;

final class UpdateUserInputValidatorTest extends TestCase
{
    private UpdateUserInputValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new UpdateUserInputValidator(
            new PasswordPolicy()
        );
    }

    public function testNormalizesUpdateWithoutPassword(): void
    {
        $input = $this->validator->validate([
            'nome' => '  Usuario Atualizado  ',
            'email' => '  USUARIO@EXAMPLE.COM  ',
            'usuario' => '  Usuario.Teste  ',
            'senha' => '',
            'perfil' => ' operador ',
            'ativo' => true,
        ]);

        self::assertSame('Usuario Atualizado', $input->name);
        self::assertSame('usuario@example.com', $input->email);
        self::assertSame('usuario.teste', $input->username);
        self::assertNull($input->password);
        self::assertSame(UserProfile::Operator, $input->profile);
        self::assertTrue($input->active);
    }

    public function testAcceptsOptionalStrongPassword(): void
    {
        $data = self::validInput();
        $data['senha'] = 'NovaSenha@123';

        $input = $this->validator->validate($data);

        self::assertSame('NovaSenha@123', $input->password);
    }

    public function testReturnsRequiredFieldErrors(): void
    {
        try {
            $this->validator->validate([]);

            self::fail(
                'Era esperada uma ValidationException.'
            );
        } catch (ValidationException $exception) {
            self::assertSame(
                ['nome', 'email', 'usuario', 'perfil', 'ativo'],
                array_keys($exception->errors())
            );
            self::assertArrayNotHasKey(
                'senha',
                $exception->errors()
            );
        }
    }

    public function testRejectsNonBooleanActiveField(): void
    {
        $data = self::validInput();
        $data['ativo'] = 'true';

        try {
            $this->validator->validate($data);

            self::fail(
                'Era esperada uma ValidationException.'
            );
        } catch (ValidationException $exception) {
            self::assertArrayHasKey(
                'ativo',
                $exception->errors()
            );
        }
    }

    public function testRejectsWeakOptionalPassword(): void
    {
        $data = self::validInput();
        $data['senha'] = 'senhafraca';

        try {
            $this->validator->validate($data);

            self::fail(
                'Era esperada uma ValidationException.'
            );
        } catch (ValidationException $exception) {
            self::assertArrayHasKey(
                'senha',
                $exception->errors()
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function validInput(): array
    {
        return [
            'nome' => 'Usuario',
            'email' => 'usuario@example.com',
            'usuario' => 'usuario',
            'perfil' => 'OPERADOR',
            'ativo' => true,
        ];
    }
}
