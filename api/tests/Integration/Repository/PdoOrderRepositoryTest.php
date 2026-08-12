<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Config\Environment;
use App\Database\ConnectionFactory;
use App\Entity\OrderStatus;
use App\Entity\UserProfile;
use App\Repository\PdoOrderRepository;
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

        $users = new PdoUserRepository(
            $this->connection,
            $cipher,
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

        $this->orders = new PdoOrderRepository(
            $this->connection,
            $cipher
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
        $customerName = 'Cliente Protegido';
        $description = 'Descricao confidencial';

        $order = $this->orders->create(
            $customerName,
            $description,
            OrderStatus::Pending,
            $this->userId
        );

        self::assertGreaterThan(0, $order->id);
        self::assertSame($customerName, $order->customerName);
        self::assertSame($description, $order->description);
        self::assertSame($this->userId, $order->createdBy);

        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT
                cliente_nome_criptografado,
                descricao_criptografada
            FROM pedidos
            WHERE id = :id
            SQL
        );

        $statement->execute(['id' => $order->id]);
        $row = $statement->fetch();

        self::assertIsArray($row);
        self::assertNotSame(
            $customerName,
            $row['cliente_nome_criptografado']
        );
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
            'Cliente Original',
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
            'Cliente Atualizado',
            'Descricao Atualizada',
            OrderStatus::Completed
        );

        self::assertNotNull($updated);
        self::assertSame(
            'Cliente Atualizado',
            $updated->customerName
        );
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
                'Cliente',
                'Descricao',
                OrderStatus::Pending
            )
        );
    }
}
