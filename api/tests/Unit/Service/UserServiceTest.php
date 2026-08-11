<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Dto\CreateUserInput;
use App\Entity\UserProfile;
use App\Exception\ValidationException;
use App\Service\UserService;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryUserRepository;

final class UserServiceTest extends TestCase
{
    private InMemoryUserRepository $repository;
    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository =
            new InMemoryUserRepository();

        $this->service = new UserService(
            $this->repository
        );
    }

    public function testCreatesUserWithPasswordHash(): void
    {
        $input = self::validInput();

        $user = $this->service->create($input);

        self::assertSame($input->name, $user->name);
        self::assertSame($input->email, $user->email);
        self::assertSame(
            $input->username,
            $user->username
        );
        self::assertSame(
            UserProfile::Operator,
            $user->profile
        );

        $passwordHash =
            $this->repository->lastPasswordHash();

        self::assertIsString($passwordHash);
        self::assertNotSame(
            $input->password,
            $passwordHash
        );
        self::assertTrue(
            password_verify(
                $input->password,
                $passwordHash
            )
        );
    }

    public function testRejectsDuplicatedEmailAndUsername(): void
    {
        $input = self::validInput();

        $this->service->create($input);

        try {
            $this->service->create($input);

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
                    'email' =>
                        'Este e-mail ja esta cadastrado.',
                    'usuario' =>
                        'Este usuario ja esta cadastrado.',
                ],
                $exception->errors()
            );
        }
    }

    public function testListsAndFindsUsers(): void
    {
        $user = $this->service->create(
            self::validInput()
        );

        self::assertSame(
            $user,
            $this->service->findById($user->id)
        );

        self::assertSame(
            [$user],
            $this->service->findAll()
        );

        self::assertNull(
            $this->service->findById(999)
        );
    }

    private static function validInput(): CreateUserInput
    {
        return new CreateUserInput(
            name: 'Vagner Portella',
            email: 'vagner@example.com',
            username: 'vagner',
            password: 'SenhaSegura123',
            profile: UserProfile::Operator
        );
    }
}
