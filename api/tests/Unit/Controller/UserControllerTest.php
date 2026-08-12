<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\UserController;
use App\Entity\UserProfile;
use App\Exception\ValidationException;
use App\Http\Request;
use App\Service\CreateUserInputValidator;
use App\Service\UserService;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryUserRepository;

final class UserControllerTest extends TestCase
{
    public function testListsUsersWithoutSensitiveData(): void
    {
        $repository = new InMemoryUserRepository();

        $repository->create(
            'Usuario Admin',
            'admin@example.com',
            'admin',
            password_hash('SenhaSegura@123', PASSWORD_DEFAULT),
            UserProfile::Admin
        );

        $response = (new UserController(
            new UserService($repository),
            new CreateUserInputValidator()
        ))->index();

        self::assertSame(200, $response->status());

        $body = json_decode(
            $response->body(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame('admin', $body['users'][0]['usuario']);
        self::assertSame('ADMIN', $body['users'][0]['perfil']);
        self::assertTrue($body['users'][0]['ativo']);
        self::assertArrayNotHasKey(
            'senha_hash',
            $body['users'][0]
        );
        self::assertStringNotContainsString(
            'SenhaSegura@123',
            $response->body()
        );
    }

    public function testCreatesValidatedOperatorWithoutPasswordInResponse(): void
    {
        $repository = new InMemoryUserRepository();
        $controller = new UserController(
            new UserService($repository),
            new CreateUserInputValidator()
        );

        $response = $controller->create(
            $this->jsonRequest([
                'nome' => '  Usuario Operador  ',
                'email' => '  OPERADOR@EXAMPLE.COM  ',
                'usuario' => '  Operador.Teste  ',
                'senha' => 'SenhaSegura@123',
                'perfil' => 'OPERADOR',
            ])
        );

        self::assertSame(201, $response->status());

        $body = json_decode(
            $response->body(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame(
            'Usuario Operador',
            $body['user']['nome']
        );
        self::assertSame(
            'operador@example.com',
            $body['user']['email']
        );
        self::assertSame(
            'operador.teste',
            $body['user']['usuario']
        );
        self::assertSame(
            'OPERADOR',
            $body['user']['perfil']
        );
        self::assertArrayNotHasKey('senha', $body['user']);
        self::assertArrayNotHasKey('senha_hash', $body['user']);
        self::assertTrue(
            password_verify(
                'SenhaSegura@123',
                (string) $repository->lastPasswordHash()
            )
        );
    }

    public function testRejectsWeakPasswordDuringCreation(): void
    {
        $controller = new UserController(
            new UserService(new InMemoryUserRepository()),
            new CreateUserInputValidator()
        );

        try {
            $controller->create(
                $this->jsonRequest([
                    'nome' => 'Usuario Operador',
                    'email' => 'operador@example.com',
                    'usuario' => 'operador',
                    'senha' => 'senhafraca',
                    'perfil' => 'OPERADOR',
                ])
            );

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
     * @param array<string, mixed> $body
     */
    private function jsonRequest(array $body): Request
    {
        return new Request(
            'POST',
            '/usuarios',
            body: json_encode($body, JSON_THROW_ON_ERROR)
        );
    }
}
