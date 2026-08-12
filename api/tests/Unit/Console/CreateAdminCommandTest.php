<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use App\Console\CreateAdminCommand;
use App\Entity\UserProfile;
use App\Exception\ValidationException;
use App\Service\CreateUserInputValidator;
use App\Service\UserService;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryUserRepository;

final class CreateAdminCommandTest extends TestCase
{
    private InMemoryUserRepository $repository;
    private CreateAdminCommand $command;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new InMemoryUserRepository();
        $this->command = new CreateAdminCommand(
            new CreateUserInputValidator(),
            new UserService($this->repository)
        );
    }

    public function testCreatesNormalizedAdminUser(): void
    {
        $user = $this->command->execute([
            'nome' => '  Administrador Master  ',
            'email' => '  ADMIN@EXAMPLE.COM  ',
            'usuario' => '  Admin.Master  ',
            'senha' => 'SenhaSegura123',
        ]);

        self::assertSame('Administrador Master', $user->name);
        self::assertSame('admin@example.com', $user->email);
        self::assertSame('admin.master', $user->username);
        self::assertSame(UserProfile::Admin, $user->profile);
        self::assertTrue($user->active);
        self::assertTrue(
            password_verify(
                'SenhaSegura123',
                (string) $this->repository->lastPasswordHash()
            )
        );
    }

    public function testAlwaysForcesAdminProfile(): void
    {
        $user = $this->command->execute([
            'nome' => 'Administrador',
            'email' => 'admin@example.com',
            'usuario' => 'admin',
            'senha' => 'SenhaSegura123',
            'perfil' => 'OPERADOR',
        ]);

        self::assertSame(UserProfile::Admin, $user->profile);
    }

    public function testRejectsInvalidData(): void
    {
        try {
            $this->command->execute([]);

            self::fail(
                'Era esperada uma ValidationException.'
            );
        } catch (ValidationException $exception) {
            self::assertArrayHasKey(
                'nome',
                $exception->errors()
            );
            self::assertArrayHasKey(
                'email',
                $exception->errors()
            );
            self::assertArrayHasKey(
                'usuario',
                $exception->errors()
            );
            self::assertArrayHasKey(
                'senha',
                $exception->errors()
            );
        }
    }

    public function testRejectsDuplicateUsernameAndEmail(): void
    {
        $data = [
            'nome' => 'Administrador',
            'email' => 'admin@example.com',
            'usuario' => 'admin',
            'senha' => 'SenhaSegura123',
        ];

        $this->command->execute($data);

        try {
            $this->command->execute($data);

            self::fail(
                'Era esperada uma ValidationException.'
            );
        } catch (ValidationException $exception) {
            self::assertSame(
                'Este e-mail ja esta cadastrado.',
                $exception->errors()['email'] ?? null
            );
            self::assertSame(
                'Este usuario ja esta cadastrado.',
                $exception->errors()['usuario'] ?? null
            );
        }
    }
}
