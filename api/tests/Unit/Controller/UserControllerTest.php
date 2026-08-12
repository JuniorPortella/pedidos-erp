<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\UserController;
use App\Dto\AuthenticatedUser;
use App\Dto\TokenClaims;
use App\Entity\TokenType;
use App\Entity\User;
use App\Entity\UserProfile;
use App\Exception\ValidationException;
use App\Http\Request;
use App\Service\CreateUserInputValidator;
use App\Service\PasswordPolicy;
use App\Service\UpdateUserInputValidator;
use App\Service\UserService;
use DateTimeImmutable;
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
            new CreateUserInputValidator(),
            new UpdateUserInputValidator(
                new PasswordPolicy()
            )
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
            new CreateUserInputValidator(),
            new UpdateUserInputValidator(
                new PasswordPolicy()
            )
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
            new CreateUserInputValidator(),
            new UpdateUserInputValidator(
                new PasswordPolicy()
            )
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

    public function testUpdatesUserWithOptionalPassword(): void
    {
        $repository = new InMemoryUserRepository();
        $target = $repository->create(
            'Usuario Original',
            'original@example.com',
            'original',
            password_hash('SenhaOriginal@123', PASSWORD_DEFAULT),
            UserProfile::Operator
        );

        $controller = $this->controller($repository);

        $response = $controller->update(
            $this->jsonRequest([
                'nome' => 'Usuario Atualizado',
                'email' => 'atualizado@example.com',
                'usuario' => 'atualizado',
                'senha' => 'NovaSenha@123',
                'perfil' => 'OPERADOR',
                'ativo' => true,
            ], 'PUT'),
            (string) $target->id,
            $this->authenticatedAdmin(99)
        );

        self::assertSame(200, $response->status());

        $body = json_decode(
            $response->body(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame(
            'atualizado',
            $body['user']['usuario']
        );
        self::assertTrue(
            password_verify(
                'NovaSenha@123',
                (string) $repository->lastPasswordHash()
            )
        );
        self::assertArrayNotHasKey('senha', $body['user']);
    }

    public function testDeletesUserWithEmptyResponse(): void
    {
        $repository = new InMemoryUserRepository();
        $target = $repository->create(
            'Usuario Excluido',
            'excluido@example.com',
            'excluido',
            password_hash('SenhaSegura@123', PASSWORD_DEFAULT),
            UserProfile::Operator
        );

        $response = $this->controller($repository)->delete(
            (string) $target->id,
            $this->authenticatedAdmin(99)
        );

        self::assertSame(204, $response->status());
        self::assertSame('', $response->body());
        self::assertNull($repository->findById($target->id));
    }

    public function testRejectsInvalidUserId(): void
    {
        $this->expectException(ValidationException::class);

        $this->controller(
            new InMemoryUserRepository()
        )->delete(
            'id-invalido',
            $this->authenticatedAdmin(99)
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    private function jsonRequest(
        array $body,
        string $method = 'POST'
    ): Request
    {
        return new Request(
            $method,
            '/usuarios',
            headers: ['content-type' => 'application/json'],
            body: json_encode($body, JSON_THROW_ON_ERROR)
        );
    }

    private function controller(
        InMemoryUserRepository $repository
    ): UserController {
        return new UserController(
            new UserService($repository),
            new CreateUserInputValidator(),
            new UpdateUserInputValidator(
                new PasswordPolicy()
            )
        );
    }

    private function authenticatedAdmin(
        int $id
    ): AuthenticatedUser {
        $now = new DateTimeImmutable();

        return new AuthenticatedUser(
            new User(
                id: $id,
                name: 'Administrador',
                email: 'admin@example.com',
                username: 'admin',
                profile: UserProfile::Admin,
                active: true,
                createdAt: $now,
                updatedAt: $now
            ),
            new TokenClaims(
                userId: $id,
                jti: str_repeat('a', 64),
                type: TokenType::Access,
                issuedAt: 1_000,
                notBefore: 1_000,
                expiresAt: 2_000,
                csrfHash: str_repeat('b', 64)
            )
        );
    }
}
