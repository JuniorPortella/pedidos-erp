<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Config\Environment;
use App\Database\ConnectionFactory;
use App\Entity\OrderStatus;
use App\Entity\UserProfile;
use App\Repository\PdoOrderRepository;
use App\Repository\PdoClientRepository;
use App\Repository\PdoUserRepository;
use App\Security\DataCipher;
use App\Security\LookupHasher;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoOrderRepositoryTest extends TestCase
{
    private PDO $connection;
    private PdoOrderRepository $orders;
    private int $userId;
    private int $clientId;
    private DataCipher $cipher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = ConnectionFactory::create();
        $this->connection->beginTransaction();

        $this->cipher = new DataCipher(
            Environment::getRequired(
                'DATA_ENCRYPTION_KEY'
            )
        );

        $users = new PdoUserRepository(
            $this->connection,
            $this->cipher,
            new LookupHasher(
                Environment::getRequired(
                    'DATA_LOOKUP_KEY'
                )
            )
        );

        $suffix = bin2hex(random_bytes(4));

        $this->userId = $users->create(
            'Criador de Pedidos',
            "pedidos.{$suffix}@example.test",
            "pedidos_{$suffix}",
            password_hash(
                'SenhaSegura@123',
                PASSWORD_DEFAULT
            ),
            UserProfile::Operator
        )->id;

        $clients = new PdoClientRepository(
            $this->connection,
            $this->cipher
        );
        $this->clientId = $clients->create(
            'Cliente Protegido',
            '11999999999'
        )->id;

        $this->orders = new PdoOrderRepository(
            $this->connection,
            $this->cipher
        );
    }

    protected function tearDown(): void
    {
        if ($this->connection->inTransaction()) {
            $this->connection->rollBack();
        }

        parent::tearDown();
    }

    public function testCreatesAndReadsEncryptedOrder(): void
    {
        $description = 'Descricao confidencial';

        $order = $this->orders->create(
            $this->clientId,
            $description,
            OrderStatus::Pending,
            $this->userId
        );

        self::assertGreaterThan(0, $order->id);
        self::assertSame($this->clientId, $order->clientId);
        self::assertSame($description, $order->description);
        self::assertSame($this->userId, $order->createdBy);

        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT
                cliente_id,
                descricao_criptografada
            FROM pedidos
            WHERE id = :id
            SQL
        );

        $statement->execute(['id' => $order->id]);
        $row = $statement->fetch();

        self::assertIsArray($row);
        self::assertSame($this->clientId, (int) $row['cliente_id']);
        self::assertNotSame(
            $description,
            $row['descricao_criptografada']
        );
        self::assertNotNull(
            $this->orders->findById($order->id)
        );
    }

    public function testListsAndUpdatesOrder(): void
    {
        $order = $this->orders->create(
            $this->clientId,
            'Descricao Original',
            OrderStatus::Pending,
            $this->userId
        );

        $ids = array_map(
            static fn ($listedOrder): int =>
                $listedOrder->id,
            $this->orders->findAll()
        );

        self::assertContains($order->id, $ids);

        $updated = $this->orders->update(
            $order->id,
            $this->clientId,
            'Descricao Atualizada',
            OrderStatus::Completed
        );

        self::assertNotNull($updated);
        self::assertSame($this->clientId, $updated->clientId);
        self::assertSame(
            OrderStatus::Completed,
            $updated->status
        );
        self::assertSame($this->userId, $updated->createdBy);
    }

    public function testReturnsNullForMissingOrder(): void
    {
        self::assertNull($this->orders->findById(999999999));
        self::assertNull(
            $this->orders->update(
                999999999,
                $this->clientId,
                'Descricao',
                OrderStatus::Pending
            )
        );
    }

    public function testSchemaStoresOnlyClientForeignKey(): void
    {
        $statement = $this->connection->query(
            <<<'SQL'
            SELECT
                column_name AS name,
                is_nullable AS nullable
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'pedidos'
              AND column_name IN (
                  'cliente_id',
                  'cliente_nome_criptografado'
              )
            ORDER BY ordinal_position
            SQL
        );

        self::assertSame(
            [
                [
                    'name' => 'cliente_id',
                    'nullable' => 'NO',
                ],
            ],
            $statement->fetchAll()
        );

        $foreignKey = $this->connection->query(
            <<<'SQL'
            SELECT referenced_table_name
            FROM information_schema.key_column_usage
            WHERE table_schema = DATABASE()
              AND table_name = 'pedidos'
              AND column_name = 'cliente_id'
              AND referenced_table_name IS NOT NULL
            SQL
        )->fetchColumn();

        self::assertSame('clientes', $foreignKey);
    }

}
