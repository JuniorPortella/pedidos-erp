<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Config\Environment;
use App\Database\ConnectionFactory;
use App\Entity\User;
use App\Entity\UserProfile;
use App\Repository\PdoAuthenticationRepository;
use App\Repository\PdoUserRepository;
use App\Security\DataCipher;
use App\Security\LookupHasher;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoAuthenticationRepositoryTest extends TestCase
{
    private const PASSWORD = 'SenhaSegura123';

    private PDO $connection;
    private PdoAuthenticationRepository $authentication;
    private PdoUserRepository $users;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = ConnectionFactory::create();
        $this->connection->beginTransaction();

        $cipher = new DataCipher(
            Environment::getRequired(
                'DATA_ENCRYPTION_KEY'
            )
        );

        $lookupHasher = new LookupHasher(
            Environment::getRequired(
                'DATA_LOOKUP_KEY'
            )
        );

        $this->users = new PdoUserRepository(
            $this->connection,
            $cipher,
            $lookupHasher
        );

        $this->authentication =
            new PdoAuthenticationRepository(
                $this->connection,
                $this->users
            );
    }

    protected function tearDown(): void
    {
        if ($this->connection->inTransaction()) {
            $this->connection->rollBack();
        }

        parent::tearDown();
    }

    public function testAuthenticatesValidCredentials(): void
    {
        $user = $this->createUser('usuario_valido');

        $authenticated = $this->authentication
            ->authenticate(
                $user->username,
                self::PASSWORD
            );

        self::assertInstanceOf(
            User::class,
            $authenticated
        );
        self::assertSame(
            $user->id,
            $authenticated->id
        );
    }

    public function testRejectsInvalidCredentials(): void
    {
        $user = $this->createUser('usuario_invalido');

        self::assertNull(
            $this->authentication->authenticate(
                $user->username,
                'SenhaIncorreta'
            )
        );

        self::assertNull(
            $this->authentication->authenticate(
                'usuario_inexistente',
                self::PASSWORD
            )
        );
    }

    public function testRejectsInactiveAndDeletedUsers(): void
    {
        $inactive = $this->createUser(
            'usuario_inativo'
        );

        $statement = $this->connection->prepare(
            <<<'SQL'
            UPDATE usuarios
            SET ativo = 0
            WHERE id = :id
            SQL
        );

        $statement->execute(['id' => $inactive->id]);

        self::assertNull(
            $this->authentication->authenticate(
                $inactive->username,
                self::PASSWORD
            )
        );

        $deleted = $this->createUser(
            'usuario_excluido'
        );

        $statement = $this->connection->prepare(
            <<<'SQL'
            UPDATE usuarios
            SET deleted_at = CURRENT_TIMESTAMP
            WHERE id = :id
            SQL
        );

        $statement->execute(['id' => $deleted->id]);

        self::assertNull(
            $this->authentication->authenticate(
                $deleted->username,
                self::PASSWORD
            )
        );
    }

    private function createUser(string $username): User
    {
        $suffix = bin2hex(random_bytes(4));

        return $this->users->create(
            name: 'Usuario de Autenticacao',
            email: "{$username}.{$suffix}@example.com",
            username: "{$username}_{$suffix}",
            passwordHash: password_hash(
                self::PASSWORD,
                PASSWORD_DEFAULT
            ),
            profile: UserProfile::Operator
        );
    }
}
