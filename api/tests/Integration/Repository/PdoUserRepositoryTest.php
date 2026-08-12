<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Config\Environment;
use App\Database\ConnectionFactory;
use App\Entity\User;
use App\Entity\UserProfile;
use App\Repository\PdoUserRepository;
use App\Security\DataCipher;
use App\Security\LookupHasher;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoUserRepositoryTest extends TestCase
{
    private PDO $connection;
    private PdoUserRepository $repository;
    private LookupHasher $lookupHasher;

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

        $this->lookupHasher = new LookupHasher(
            Environment::getRequired(
                'DATA_LOOKUP_KEY'
            )
        );

        $this->repository = new PdoUserRepository(
            $this->connection,
            $cipher,
            $this->lookupHasher
        );
    }

    protected function tearDown(): void
    {
        if ($this->connection->inTransaction()) {
            $this->connection->rollBack();
        }

        parent::tearDown();
    }

    public function testCreatesUserWithProtectedData(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $name = 'Usuario de Teste';
        $email = "usuario.{$suffix}@example.com";
        $username = "usuario_{$suffix}";
        $password = 'SenhaSegura123';

        $user = $this->repository->create(
            name: $name,
            email: $email,
            username: $username,
            passwordHash: password_hash(
                $password,
                PASSWORD_DEFAULT
            ),
            profile: UserProfile::Operator
        );

        self::assertGreaterThan(0, $user->id);
        self::assertSame($name, $user->name);
        self::assertSame($email, $user->email);
        self::assertSame($username, $user->username);
        self::assertSame(
            UserProfile::Operator,
            $user->profile
        );
        self::assertTrue($user->active);

        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT
                nome_criptografado,
                email_criptografado,
                email_hash,
                senha_hash
            FROM usuarios
            WHERE id = :id
            SQL
        );

        $statement->execute(['id' => $user->id]);

        $row = $statement->fetch();

        self::assertIsArray($row);
        self::assertNotSame(
            $name,
            $row['nome_criptografado']
        );
        self::assertNotSame(
            $email,
            $row['email_criptografado']
        );
        self::assertSame(
            $this->lookupHasher->hash(
                $email,
                'usuarios.email'
            ),
            $row['email_hash']
        );
        self::assertTrue(
            password_verify(
                $password,
                $row['senha_hash']
            )
        );
    }

    public function testFindsAndListsExistingUsers(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $email = "consulta.{$suffix}@example.com";
        $username = "consulta_{$suffix}";

        $createdUser = $this->repository->create(
            name: 'Usuario para Consulta',
            email: $email,
            username: $username,
            passwordHash: password_hash(
                'SenhaSegura123',
                PASSWORD_DEFAULT
            ),
            profile: UserProfile::Operator
        );

        $foundById = $this->repository->findById(
            $createdUser->id
        );

        self::assertInstanceOf(User::class, $foundById);
        self::assertSame($createdUser->id, $foundById->id);

        $foundByUsername = $this->repository
            ->findByUsername($username);

        self::assertInstanceOf(
            User::class,
            $foundByUsername
        );
        self::assertSame(
            $createdUser->id,
            $foundByUsername->id
        );

        self::assertTrue(
            $this->repository->emailExists($email)
        );
        self::assertTrue(
            $this->repository->usernameExists($username)
        );

        self::assertFalse(
            $this->repository->emailExists(
                "inexistente.{$suffix}@example.com"
            )
        );
        self::assertFalse(
            $this->repository->usernameExists(
                "inexistente_{$suffix}"
            )
        );

        $ids = array_map(
            static fn (User $user): int => $user->id,
            $this->repository->findAll()
        );

        self::assertContains($createdUser->id, $ids);
    }

    public function testIgnoresLogicallyDeletedUsers(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $email = "excluido.{$suffix}@example.com";
        $username = "excluido_{$suffix}";

        $user = $this->repository->create(
            name: 'Usuario Excluido',
            email: $email,
            username: $username,
            passwordHash: password_hash(
                'SenhaSegura123',
                PASSWORD_DEFAULT
            ),
            profile: UserProfile::Operator
        );

        $statement = $this->connection->prepare(
            <<<'SQL'
            UPDATE usuarios
            SET deleted_at = CURRENT_TIMESTAMP
            WHERE id = :id
            SQL
        );

        $statement->execute(['id' => $user->id]);

        self::assertSame(1, $statement->rowCount());
        self::assertNull(
            $this->repository->findById($user->id)
        );
        self::assertNull(
            $this->repository->findByUsername($username)
        );

        $ids = array_map(
            static fn (User $listedUser): int => $listedUser->id,
            $this->repository->findAll()
        );

        self::assertNotContains($user->id, $ids);

        self::assertTrue(
            $this->repository->emailExists($email)
        );
        self::assertTrue(
            $this->repository->usernameExists($username)
        );
    }

    public function testUpdatesAndSoftDeletesUser(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $user = $this->repository->create(
            name: 'Usuario Original',
            email: "original.{$suffix}@example.com",
            username: "original_{$suffix}",
            passwordHash: password_hash(
                'SenhaOriginal@123',
                PASSWORD_DEFAULT
            ),
            profile: UserProfile::Operator
        );

        $originalPasswordHash = $this->passwordHash($user->id);
        $updatedEmail = "atualizado.{$suffix}@example.com";
        $updatedUsername = "atualizado_{$suffix}";

        $updated = $this->repository->update(
            id: $user->id,
            name: 'Usuario Atualizado',
            email: $updatedEmail,
            username: $updatedUsername,
            passwordHash: null,
            profile: UserProfile::Admin,
            active: false
        );

        self::assertInstanceOf(User::class, $updated);
        self::assertSame('Usuario Atualizado', $updated->name);
        self::assertSame($updatedEmail, $updated->email);
        self::assertSame($updatedUsername, $updated->username);
        self::assertSame(UserProfile::Admin, $updated->profile);
        self::assertFalse($updated->active);
        self::assertSame(
            $originalPasswordHash,
            $this->passwordHash($user->id)
        );
        self::assertFalse(
            $this->repository->emailExists(
                $updatedEmail,
                $user->id
            )
        );
        self::assertFalse(
            $this->repository->usernameExists(
                $updatedUsername,
                $user->id
            )
        );

        $newPasswordHash = password_hash(
            'NovaSenha@123',
            PASSWORD_DEFAULT
        );

        $this->repository->update(
            id: $user->id,
            name: $updated->name,
            email: $updated->email,
            username: $updated->username,
            passwordHash: $newPasswordHash,
            profile: $updated->profile,
            active: true
        );

        self::assertSame(
            $newPasswordHash,
            $this->passwordHash($user->id)
        );
        self::assertTrue($this->repository->softDelete($user->id));
        self::assertNull($this->repository->findById($user->id));
        self::assertFalse($this->repository->softDelete($user->id));
    }

    private function passwordHash(int $userId): string
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT senha_hash
            FROM usuarios
            WHERE id = :id
            SQL
        );

        $statement->execute(['id' => $userId]);

        return (string) $statement->fetchColumn();
    }
}
