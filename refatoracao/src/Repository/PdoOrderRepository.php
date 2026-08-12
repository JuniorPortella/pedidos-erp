<?php

declare(strict_types=1);

namespace Refatoracao\Repository;

use PDO;
use PDOStatement;
use Refatoracao\Entity\Order;
use RuntimeException;

final readonly class PdoOrderRepository implements OrderRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function insert(string $customerName): Order
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO pedidos (cliente_nome)
            VALUES (:cliente_nome)
            SQL
        );

        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException(
                'Nao foi possivel preparar a insercao do pedido.'
            );
        }

        $statement->execute([
            'cliente_nome' => $customerName,
        ]);

        $id = $this->connection->lastInsertId();

        if ($id === false || (int) $id <= 0) {
            throw new RuntimeException(
                'O banco nao retornou o identificador do pedido.'
            );
        }

        return new Order(
            id: (int) $id,
            customerName: $customerName
        );
    }
}
