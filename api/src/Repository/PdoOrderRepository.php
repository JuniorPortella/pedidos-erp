<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\OrderStatus;
use App\Security\DataCipher;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final readonly class PdoOrderRepository implements OrderRepository
{
    private const CUSTOMER_NAME_CONTEXT =
        'pedidos.cliente_nome';
    private const DESCRIPTION_CONTEXT =
        'pedidos.descricao';

    public function __construct(
        private PDO $connection,
        private DataCipher $cipher
    ) {
    }

    public function create(
        string $customerName,
        string $description,
        OrderStatus $status,
        int $createdBy
    ): Order {
        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO pedidos (
                cliente_nome_criptografado,
                descricao_criptografada,
                status,
                criado_por
            ) VALUES (
                :customer_name,
                :description,
                :status,
                :created_by
            )
            SQL
        );

        $statement->execute([
            'customer_name' => $this->cipher->encrypt(
                $customerName,
                self::CUSTOMER_NAME_CONTEXT
            ),
            'description' => $this->cipher->encrypt(
                $description,
                self::DESCRIPTION_CONTEXT
            ),
            'status' => $status->value,
            'created_by' => $createdBy,
        ]);

        $order = $this->findById(
            (int) $this->connection->lastInsertId()
        );

        if ($order === null) {
            throw new RuntimeException(
                'Pedido criado, mas nao foi possivel consulta-lo.'
            );
        }

        return $order;
    }

    public function update(
        int $id,
        string $customerName,
        string $description,
        OrderStatus $status
    ): ?Order {
        $statement = $this->connection->prepare(
            <<<'SQL'
            UPDATE pedidos
            SET
                cliente_nome_criptografado = :customer_name,
                descricao_criptografada = :description,
                status = :status,
                updated_at = UTC_TIMESTAMP()
            WHERE id = :id
            SQL
        );

        $statement->execute([
            'id' => $id,
            'customer_name' => $this->cipher->encrypt(
                $customerName,
                self::CUSTOMER_NAME_CONTEXT
            ),
            'description' => $this->cipher->encrypt(
                $description,
                self::DESCRIPTION_CONTEXT
            ),
            'status' => $status->value,
        ]);

        return $this->findById($id);
    }

    public function findById(int $id): ?Order
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT
                id,
                cliente_nome_criptografado,
                descricao_criptografada,
                status,
                criado_por,
                created_at,
                updated_at
            FROM pedidos
            WHERE id = :id
            LIMIT 1
            SQL
        );

        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        return $row === false
            ? null
            : $this->hydrate($row);
    }

    public function findAll(): array
    {
        $statement = $this->connection->query(
            <<<'SQL'
            SELECT
                id,
                cliente_nome_criptografado,
                descricao_criptografada,
                status,
                criado_por,
                created_at,
                updated_at
            FROM pedidos
            ORDER BY created_at DESC, id DESC
            SQL
        );

        return array_map(
            $this->hydrate(...),
            $statement->fetchAll()
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Order
    {
        return new Order(
            id: (int) $row['id'],
            customerName: $this->cipher->decrypt(
                (string) $row['cliente_nome_criptografado'],
                self::CUSTOMER_NAME_CONTEXT
            ),
            description: $this->cipher->decrypt(
                (string) $row['descricao_criptografada'],
                self::DESCRIPTION_CONTEXT
            ),
            status: OrderStatus::from(
                (string) $row['status']
            ),
            createdBy: (int) $row['criado_por'],
            createdAt: new DateTimeImmutable(
                (string) $row['created_at']
            ),
            updatedAt: new DateTimeImmutable(
                (string) $row['updated_at']
            )
        );
    }
}
