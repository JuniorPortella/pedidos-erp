<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Config\Environment;
use App\Database\ConnectionFactory;
use App\Repository\PdoClientRepository;
use App\Security\DataCipher;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoClientRepositoryTest extends TestCase
{
    private PDO $connection;
    private PdoClientRepository $clients;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = ConnectionFactory::create();
        $this->connection->beginTransaction();
        $this->clients = new PdoClientRepository(
            $this->connection,
            new DataCipher(
                Environment::getRequired('DATA_ENCRYPTION_KEY')
            )
        );
    }

    protected function tearDown(): void
    {
        if ($this->connection->inTransaction()) {
            $this->connection->rollBack();
        }

        parent::tearDown();
    }

    public function testPersistsEncryptedClientAndDecryptsIt(): void
    {
        $name = 'Cliente Protegido';
        $phone = '+55 (11) 99999-9999';
        $client = $this->clients->create($name, $phone);

        self::assertSame($name, $client->name);
        self::assertSame($phone, $client->phone);

        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT nome_criptografado, telefone_criptografado
            FROM clientes
            WHERE id = :id
            SQL
        );
        $statement->execute(['id' => $client->id]);
        $row = $statement->fetch();

        self::assertIsArray($row);
        self::assertNotSame($name, $row['nome_criptografado']);
        self::assertNotSame($phone, $row['telefone_criptografado']);
        self::assertSame(
            $client->id,
            $this->clients->findById($client->id)?->id
        );
    }

    public function testListsUpdatesAndSoftDeletesClient(): void
    {
        $client = $this->clients->create(
            'Cliente Original',
            '11999999999'
        );

        self::assertContains(
            $client->id,
            array_map(
                static fn ($listed): int => $listed->id,
                $this->clients->findAll()
            )
        );

        $updated = $this->clients->update(
            $client->id,
            'Cliente Atualizado',
            '11888888888'
        );

        self::assertSame('Cliente Atualizado', $updated?->name);
        self::assertTrue($this->clients->softDelete($client->id));
        self::assertNull($this->clients->findById($client->id));
        self::assertFalse($this->clients->softDelete($client->id));
    }

    public function testReturnsNullForMissingClient(): void
    {
        self::assertNull($this->clients->findById(999999999));
        self::assertNull(
            $this->clients->update(
                999999999,
                'Cliente',
                '11999999999'
            )
        );
    }
}
