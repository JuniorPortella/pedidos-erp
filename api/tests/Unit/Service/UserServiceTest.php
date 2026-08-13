<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Dto\CreateUserInput;
use App\Dto\UpdateUserInput;
use App\Entity\UserProfile;
use App\Exception\ValidationException;
use App\Exception\UserNotFoundException;
use App\Repository\RefreshTokenRepository;
use App\Service\UserService;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryUserRepository;

final class UserServiceTest extends TestCase
{
    private InMemoryUserRepository $repository;
    private RefreshTokenRepository $refreshTokens;
    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository =
            new InMemoryUserRepository();

        $this->refreshTokens = $this->createMock(
            RefreshTokenRepository::class
        );

        $this->service = new UserService(
            $this->repository,
            $this->refreshTokens
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

    public function testHidesAndProtectsConfiguredUser(): void
    {
        $protectedUser = $this->repository->create(
            'Administrador Principal',
            'principal@example.com',
            'principal',
            password_hash('SenhaSegura@123', PASSWORD_DEFAULT),
            UserProfile::Admin
        );

        $visibleUser = $this->repository->create(
            'Administrador Visivel',
            'visivel@example.com',
            'visivel',
            password_hash('SenhaSegura@123', PASSWORD_DEFAULT),
            UserProfile::Admin
        );

        $service = new UserService(
            $this->repository,
            $this->refreshTokens,
            protectedUserId: $protectedUser->id
        );

        self::assertSame([$visibleUser], $service->findAll());
        self::assertNull($service->findById($protectedUser->id));

        try {
            $service->update(
                $protectedUser->id,
                self::validUpdateInput(),
                actorId: $visibleUser->id
            );

            self::fail(
                'Era esperada uma UserNotFoundException.'
            );
        } catch (UserNotFoundException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(UserNotFoundException::class);

        $service->delete(
            $protectedUser->id,
            actorId: $visibleUser->id
        );
    }

    public function testUpdatesUserWithoutChangingPassword(): void
    {
        $user = $this->service->create(self::validInput());
        $passwordHash = $this->repository->lastPasswordHash();

        $this->refreshTokens
            ->expects(self::never())
            ->method('revokeAllForUser');

        $updated = $this->service->update(
            $user->id,
            self::validUpdateInput(),
            actorId: 99
        );

        self::assertSame('Nome Atualizado', $updated->name);
        self::assertSame(
            'atualizado@example.com',
            $updated->email
        );
        self::assertSame('atualizado', $updated->username);
        self::assertSame(
            UserProfile::Operator,
            $updated->profile
        );
        self::assertTrue($updated->active);
        self::assertSame(
            $passwordHash,
            $this->repository->lastPasswordHash()
        );
    }

    public function testUpdatesPasswordAndRevokesRefreshTokens(): void
    {
        $user = $this->service->create(self::validInput());

        $this->refreshTokens
            ->expects(self::once())
            ->method('revokeAllForUser')
            ->with($user->id);

        $input = self::validUpdateInput(
            password: 'NovaSenha@123'
        );

        $this->service->update(
            $user->id,
            $input,
            actorId: 99
        );

        self::assertTrue(
            password_verify(
                'NovaSenha@123',
                (string) $this->repository->lastPasswordHash()
            )
        );
    }

    public function testDeactivatesUserAndRevokesRefreshTokens(): void
    {
        $user = $this->service->create(self::validInput());

        $this->refreshTokens
            ->expects(self::once())
            ->method('revokeAllForUser')
            ->with($user->id);

        $updated = $this->service->update(
            $user->id,
            self::validUpdateInput(active: false),
            actorId: 99
        );

        self::assertFalse($updated->active);
    }

    public function testRejectsSelfDeactivationAndProfileRemoval(): void
    {
        $admin = $this->repository->create(
            'Administrador',
            'admin@example.com',
            'admin',
            password_hash('SenhaSegura@123', PASSWORD_DEFAULT),
            UserProfile::Admin
        );

        try {
            $this->service->update(
                $admin->id,
                self::validUpdateInput(
                    profile: UserProfile::Operator,
                    active: false
                ),
                actorId: $admin->id
            );

            self::fail(
                'Era esperada uma ValidationException.'
            );
        } catch (ValidationException $exception) {
            self::assertArrayHasKey(
                'ativo',
                $exception->errors()
            );
            self::assertArrayHasKey(
                'perfil',
                $exception->errors()
            );
        }
    }

    public function testRejectsUpdateWithAnotherUsersIdentifiers(): void
    {
        $first = $this->service->create(self::validInput());

        $second = $this->repository->create(
            'Outro Usuario',
            'outro@example.com',
            'outro',
            password_hash('SenhaSegura@123', PASSWORD_DEFAULT),
            UserProfile::Operator
        );

        try {
            $this->service->update(
                $first->id,
                new UpdateUserInput(
                    name: 'Duplicado',
                    email: $second->email,
                    username: $second->username,
                    password: null,
                    profile: UserProfile::Operator,
                    active: true
                ),
                actorId: 99
            );

            self::fail(
                'Era esperada uma ValidationException.'
            );
        } catch (ValidationException $exception) {
            self::assertArrayHasKey(
                'email',
                $exception->errors()
            );
            self::assertArrayHasKey(
                'usuario',
                $exception->errors()
            );
        }
    }

    public function testSoftDeletesUserAndRevokesRefreshTokens(): void
    {
        $user = $this->service->create(self::validInput());

        $this->refreshTokens
            ->expects(self::once())
            ->method('revokeAllForUser')
            ->with($user->id);

        $this->service->delete($user->id, actorId: 99);

        self::assertNull(
            $this->repository->findById($user->id)
        );
    }

    public function testRejectsSelfDeletion(): void
    {
        $admin = $this->service->create(
            new CreateUserInput(
                name: 'Administrador',
                email: 'admin@example.com',
                username: 'admin',
                password: 'SenhaSegura@123',
                profile: UserProfile::Admin
            )
        );

        $this->expectException(ValidationException::class);

        $this->service->delete($admin->id, $admin->id);
    }

    public function testRejectsMissingUserDuringUpdateAndDelete(): void
    {
        try {
            $this->service->update(
                999,
                self::validUpdateInput(),
                actorId: 1
            );

            self::fail(
                'Era esperada uma UserNotFoundException.'
            );
        } catch (UserNotFoundException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(UserNotFoundException::class);

        $this->service->delete(999, actorId: 1);
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

    private static function validUpdateInput(
        ?string $password = null,
        UserProfile $profile = UserProfile::Operator,
        bool $active = true
    ): UpdateUserInput {
        return new UpdateUserInput(
            name: 'Nome Atualizado',
            email: 'atualizado@example.com',
            username: 'atualizado',
            password: $password,
            profile: $profile,
            active: $active
        );
    }
}
