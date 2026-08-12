<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserProfile;
use App\Security\DataCipher;
use App\Security\LookupHasher;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class PdoUserRepository implements UserRepository
{
    private const NAME_CONTEXT = 'usuarios.nome';
    private const EMAIL_CONTEXT = 'usuarios.email';

    public function __construct(
        private readonly PDO $connection,
        private readonly DataCipher $cipher,
        private readonly LookupHasher $lookupHasher
    ) {
    }

    public function create(
        string $name,
        string $email,
        string $username,
        string $passwordHash,
        UserProfile $profile
    ): User {
        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO usuarios (
                nome_criptografado,
                email_criptografado,
                email_hash,
                usuario,
                senha_hash,
                perfil
            ) VALUES (
                :encrypted_name,
                :encrypted_email,
                :email_hash,
                :username,
                :password_hash,
                :profile
            )
            SQL
        );

        $statement->execute([
            'encrypted_name' => $this->cipher->encrypt(
                $name,
                self::NAME_CONTEXT
            ),
            'encrypted_email' => $this->cipher->encrypt(
                $email,
                self::EMAIL_CONTEXT
            ),
            'email_hash' => $this->lookupHasher->hash(
                $email,
                self::EMAIL_CONTEXT
            ),
            'username' => $username,
            'password_hash' => $passwordHash,
            'profile' => $profile->value,
        ]);

        $id = (int) $this->connection->lastInsertId();
        $user = $this->findById($id);

        if ($user === null) {
            throw new RuntimeException(
                'Usuario criado, mas nao foi possivel consulta-lo.'
            );
        }

        return $user;
    }

    public function findById(int $id): ?User
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT
                id,
                nome_criptografado,
                email_criptografado,
                usuario,
                perfil,
                ativo,
                created_at,
                updated_at,
                deleted_at
            FROM usuarios
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
            SQL
        );

        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        return $row === false
            ? null
            : $this->hydrate($row);
    }

    public function update(
        int $id,
        string $name,
        string $email,
        string $username,
        ?string $passwordHash,
        UserProfile $profile,
        bool $active
    ): ?User {
        $statement = $this->connection->prepare(
            <<<'SQL'
            UPDATE usuarios
            SET
                nome_criptografado = :encrypted_name,
                email_criptografado = :encrypted_email,
                email_hash = :email_hash,
                usuario = :username,
                senha_hash = COALESCE(
                    :password_hash,
                    senha_hash
                ),
                perfil = :profile,
                ativo = :active,
                updated_at = UTC_TIMESTAMP()
            WHERE id = :id
              AND deleted_at IS NULL
            SQL
        );

        $statement->execute([
            'id' => $id,
            'encrypted_name' => $this->cipher->encrypt(
                $name,
                self::NAME_CONTEXT
            ),
            'encrypted_email' => $this->cipher->encrypt(
                $email,
                self::EMAIL_CONTEXT
            ),
            'email_hash' => $this->lookupHasher->hash(
                $email,
                self::EMAIL_CONTEXT
            ),
            'username' => $username,
            'password_hash' => $passwordHash,
            'profile' => $profile->value,
            'active' => $active ? 1 : 0,
        ]);

        return $this->findById($id);
    }

    public function softDelete(int $id): bool
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            UPDATE usuarios
            SET
                ativo = 0,
                deleted_at = UTC_TIMESTAMP(),
                updated_at = UTC_TIMESTAMP()
            WHERE id = :id
              AND deleted_at IS NULL
            SQL
        );

        $statement->execute(['id' => $id]);

        return $statement->rowCount() === 1;
    }

    public function findByUsername(string $username): ?User
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT
                id,
                nome_criptografado,
                email_criptografado,
                usuario,
                perfil,
                ativo,
                created_at,
                updated_at,
                deleted_at
            FROM usuarios
            WHERE usuario = :username
              AND deleted_at IS NULL
            LIMIT 1
            SQL
        );

        $statement->execute(['username' => $username]);

        $row = $statement->fetch();

        return $row === false
            ? null
            : $this->hydrate($row);
    }

    public function emailExists(
        string $email,
        ?int $exceptUserId = null
    ): bool {
        $sql = $exceptUserId === null
            ? <<<'SQL'
              SELECT 1
              FROM usuarios
              WHERE email_hash = :email_hash
              LIMIT 1
              SQL
            : <<<'SQL'
              SELECT 1
              FROM usuarios
              WHERE email_hash = :email_hash
                AND id <> :except_user_id
              LIMIT 1
              SQL;

        $statement = $this->connection->prepare($sql);

        $parameters = [
            'email_hash' => $this->lookupHasher->hash(
                $email,
                self::EMAIL_CONTEXT
            ),
        ];

        if ($exceptUserId !== null) {
            $parameters['except_user_id'] = $exceptUserId;
        }

        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    public function usernameExists(
        string $username,
        ?int $exceptUserId = null
    ): bool {
        $sql = $exceptUserId === null
            ? <<<'SQL'
              SELECT 1
              FROM usuarios
              WHERE usuario = :username
              LIMIT 1
              SQL
            : <<<'SQL'
              SELECT 1
              FROM usuarios
              WHERE usuario = :username
                AND id <> :except_user_id
              LIMIT 1
              SQL;

        $statement = $this->connection->prepare($sql);

        $parameters = ['username' => $username];

        if ($exceptUserId !== null) {
            $parameters['except_user_id'] = $exceptUserId;
        }

        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    public function findAll(): array
    {
        $statement = $this->connection->query(
            <<<'SQL'
            SELECT
                id,
                nome_criptografado,
                email_criptografado,
                usuario,
                perfil,
                ativo,
                created_at,
                updated_at,
                deleted_at
            FROM usuarios
            WHERE deleted_at IS NULL
            ORDER BY id DESC
            SQL
        );

        $users = [];

        while (($row = $statement->fetch()) !== false) {
            $users[] = $this->hydrate($row);
        }

        return $users;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): User
    {
        $deletedAt = $row['deleted_at'] === null
            ? null
            : new DateTimeImmutable(
                (string) $row['deleted_at']
            );

        return new User(
            id: (int) $row['id'],
            name: $this->cipher->decrypt(
                (string) $row['nome_criptografado'],
                self::NAME_CONTEXT
            ),
            email: $this->cipher->decrypt(
                (string) $row['email_criptografado'],
                self::EMAIL_CONTEXT
            ),
            username: (string) $row['usuario'],
            profile: UserProfile::from(
                (string) $row['perfil']
            ),
            active: (bool) $row['ativo'],
            createdAt: new DateTimeImmutable(
                (string) $row['created_at']
            ),
            updatedAt: new DateTimeImmutable(
                (string) $row['updated_at']
            ),
            deletedAt: $deletedAt
        );
    }
}
