<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Database\ConnectionFactory;
use PDO;
use PHPUnit\Framework\TestCase;

final class ConnectionFactoryTest extends TestCase
{
    public function testCreatesWorkingMysqlConnection(): void
    {
        $connection = ConnectionFactory::create();

        self::assertInstanceOf(PDO::class, $connection);

        $result = $connection
            ->query('SELECT 1')
            ->fetchColumn();

        self::assertSame(1, (int) $result);
    }
}