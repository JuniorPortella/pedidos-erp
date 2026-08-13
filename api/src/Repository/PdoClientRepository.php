<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Client;
use App\Security\DataCipher;
use App\Security\LookupHasher;
use App\Service\PhoneNormalizer;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final readonly class PdoClientRepository implements ClientRepository
{
    private const NAME_CONTEXT = 'clientes.nome';
    private const PHONE_CONTEXT = 'clientes.telefone';

    public function __construct(
        private PDO $connection,
        private DataCipher $cipher,
        private LookupHasher $lookupHasher
    ) {
    }

    public function create(string $name, string $phone): Client
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO clientes (
                nome_criptografado,
                telefone_criptografado,
                telefone_hash
            ) VALUES (
                :name,
                :phone,
                :phone_hash
            )
            SQL
        );

        $statement->execute([
            'name' => $this->cipher->encrypt(
                $name,
                self::NAME_CONTEXT
            ),
            'phone' => $this->cipher->encrypt(
                $phone,
                self::PHONE_CONTEXT
            ),
            'phone_hash' => $this->phoneHash($phone),
        ]);

        $client = $this->findById(
            (int) $this->connection->lastInsertId()
        );

        if ($client === null) {
            throw new RuntimeException(
                'Cliente criado, mas nao foi possivel consulta-lo.'
            );
        }

        return $client;
    }

    public function update(
        int $id,
        string $name,
        string $phone
    ): ?Client {
        $statement = $this->connection->prepare(
            <<<'SQL'
            UPDATE clientes
            SET
                nome_criptografado = :name,
                telefone_criptografado = :phone,
                telefone_hash = :phone_hash,
                updated_at = UTC_TIMESTAMP()
            WHERE id = :id
              AND deleted_at IS NULL
            SQL
        );

        $statement->execute([
            'id' => $id,
            'name' => $this->cipher->encrypt(
                $name,
                self::NAME_CONTEXT
            ),
            'phone' => $this->cipher->encrypt(
                $phone,
                self::PHONE_CONTEXT
            ),
            'phone_hash' => $this->phoneHash($phone),
        ]);

        return $this->findById($id);
    }

    public function softDelete(int $id): bool
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            UPDATE clientes
            SET
                telefone_hash = NULL,
                deleted_at = UTC_TIMESTAMP(),
                updated_at = UTC_TIMESTAMP()
            WHERE id = :id
              AND deleted_at IS NULL
            SQL
        );

        $statement->execute(['id' => $id]);

        return $statement->rowCount() === 1;
    }

    public function findById(int $id): ?Client
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT
                id,
                nome_criptografado,
                telefone_criptografado,
                created_at,
                updated_at
            FROM clientes
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
            SQL
        );

        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function findAll(): array
    {
        $statement = $this->connection->query(
            <<<'SQL'
            SELECT
                id,
                nome_criptografado,
                telefone_criptografado,
                created_at,
                updated_at
            FROM clientes
            WHERE deleted_at IS NULL
            ORDER BY created_at DESC, id DESC
            SQL
        );

        return array_map(
            $this->hydrate(...),
            $statement->fetchAll()
        );
    }

    public function phoneExists(
        string $phone,
        ?int $exceptClientId = null
    ): bool {
        $sql = $exceptClientId === null
            ? <<<'SQL'
              SELECT 1
              FROM clientes
              WHERE telefone_hash = :phone_hash
                AND deleted_at IS NULL
              LIMIT 1
              SQL
            : <<<'SQL'
              SELECT 1
              FROM clientes
              WHERE telefone_hash = :phone_hash
                AND id <> :except_client_id
                AND deleted_at IS NULL
              LIMIT 1
              SQL;

        $parameters = [
            'phone_hash' => $this->phoneHash($phone),
        ];

        if ($exceptClientId !== null) {
            $parameters['except_client_id'] = $exceptClientId;
        }

        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Client
    {
        return new Client(
            id: (int) $row['id'],
            name: $this->cipher->decrypt(
                (string) $row['nome_criptografado'],
                self::NAME_CONTEXT
            ),
            phone: $this->cipher->decrypt(
                (string) $row['telefone_criptografado'],
                self::PHONE_CONTEXT
            ),
            createdAt: new DateTimeImmutable(
                (string) $row['created_at']
            ),
            updatedAt: new DateTimeImmutable(
                (string) $row['updated_at']
            )
        );
    }

    private function phoneHash(string $phone): string
    {
        return $this->lookupHasher->hash(
            PhoneNormalizer::normalize($phone),
            self::PHONE_CONTEXT
        );
    }
}
