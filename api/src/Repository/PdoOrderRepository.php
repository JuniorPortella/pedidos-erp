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
    private const DESCRIPTION_CONTEXT =
        'pedidos.descricao';

    public function __construct(
        private PDO $connection,
        private DataCipher $cipher
    ) {
    }

    public function create(
        int $clientId,
        string $description,
        string $totalAmount,
        OrderStatus $status,
        int $createdBy
    ): Order {
        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO pedidos (
                cliente_id,
                descricao_criptografada,
                valor_total,
                status,
                criado_por
            ) VALUES (
                :client_id,
                :description,
                :total_amount,
                :status,
                :created_by
            )
            SQL
        );

        $statement->execute([
            'client_id' => $clientId,
            'description' => $this->cipher->encrypt(
                $description,
                self::DESCRIPTION_CONTEXT
            ),
            'total_amount' => $totalAmount,
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
        int $clientId,
        string $description,
        string $totalAmount,
        OrderStatus $status
    ): ?Order {
        $statement = $this->connection->prepare(
            <<<'SQL'
            UPDATE pedidos
            SET
                cliente_id = :client_id,
                descricao_criptografada = :description,
                valor_total = :total_amount,
                status = :status,
                updated_at = UTC_TIMESTAMP()
            WHERE id = :id
            SQL
        );

        $statement->execute([
            'id' => $id,
            'client_id' => $clientId,
            'description' => $this->cipher->encrypt(
                $description,
                self::DESCRIPTION_CONTEXT
            ),
            'total_amount' => $totalAmount,
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
                cliente_id,
                descricao_criptografada,
                valor_total,
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
                cliente_id,
                descricao_criptografada,
                valor_total,
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
            clientId: (int) $row['cliente_id'],
            description: $this->cipher->decrypt(
                (string) $row['descricao_criptografada'],
                self::DESCRIPTION_CONTEXT
            ),
            totalAmount: (string) $row['valor_total'],
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
