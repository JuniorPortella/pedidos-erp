<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Refatoracao\Repository\PdoOrderRepository;
use RuntimeException;

final class PdoOrderRepositoryTest extends TestCase
{
    public function testInsertsOrderWithPreparedStatement(): void
    {
        $maliciousName = "Cliente'); DROP TABLE pedidos; --";

        $statement = $this->createMock(PDOStatement::class);
        $statement
            ->expects(self::once())
            ->method('execute')
            ->with(['cliente_nome' => $maliciousName])
            ->willReturn(true);

        $connection = $this->createMock(PDO::class);
        $connection
            ->expects(self::once())
            ->method('prepare')
            ->with(self::callback(
                static fn (string $sql): bool =>
                    str_contains($sql, ':cliente_nome')
                    && !str_contains($sql, $maliciousName)
            ))
            ->willReturn($statement);
        $connection
            ->expects(self::once())
            ->method('lastInsertId')
            ->willReturn('42');

        $order = (new PdoOrderRepository($connection))
            ->insert($maliciousName);

        self::assertSame(42, $order->id);
        self::assertSame($maliciousName, $order->customerName);
    }

    public function testRejectsMissingInsertedId(): void
    {
        $statement = $this->createStub(PDOStatement::class);
        $statement->method('execute')->willReturn(true);

        $connection = $this->createStub(PDO::class);
        $connection->method('prepare')->willReturn($statement);
        $connection->method('lastInsertId')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'O banco nao retornou o identificador do pedido.'
        );

        (new PdoOrderRepository($connection))->insert('Cliente');
    }
}
