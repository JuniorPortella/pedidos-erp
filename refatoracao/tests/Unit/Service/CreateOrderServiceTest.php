<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Refatoracao\Exception\OrderCreationException;
use Refatoracao\Exception\ValidationException;
use Refatoracao\Service\CreateOrderService;
use Tests\Support\ArrayLogger;
use Tests\Support\InMemoryOrderRepository;

final class CreateOrderServiceTest extends TestCase
{
    public function testCreatesOrderAndLogsSuccess(): void
    {
        $repository = new InMemoryOrderRepository();
        $logger = new ArrayLogger();
        $service = new CreateOrderService($repository, $logger);

        $order = $service->create('  Cliente Teste  ');

        self::assertSame(1, $order->id);
        self::assertSame('Cliente Teste', $order->customerName);
        self::assertCount(1, $repository->orders);
        self::assertSame(
            [
                'message' => 'Pedido criado com sucesso.',
                'context' => ['order_id' => 1],
            ],
            $logger->infoRecords[0]
        );
        self::assertSame([], $logger->errorRecords);
    }

    #[DataProvider('invalidNames')]
    public function testRejectsInvalidCustomerName(
        string $name,
        string $expectedMessage
    ): void {
        $repository = new InMemoryOrderRepository();
        $service = new CreateOrderService(
            $repository,
            new ArrayLogger()
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($expectedMessage);

        $service->create($name);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidNames(): array
    {
        return [
            'empty' => [
                '   ',
                'O nome do cliente e obrigatorio.',
            ],
            'too long' => [
                str_repeat('a', 121),
                'O nome do cliente deve possuir no maximo 120 caracteres.',
            ],
        ];
    }

    public function testLogsAndWrapsRepositoryFailure(): void
    {
        $repository = new InMemoryOrderRepository();
        $repository->mustFail = true;
        $logger = new ArrayLogger();
        $service = new CreateOrderService($repository, $logger);

        try {
            $service->create('Cliente Teste');
            self::fail('A excecao esperada nao foi lancada.');
        } catch (OrderCreationException $exception) {
            self::assertSame(
                'Nao foi possivel criar o pedido.',
                $exception->getMessage()
            );
            self::assertSame(
                'Falha simulada no banco.',
                $exception->getPrevious()?->getMessage()
            );
        }

        self::assertSame([], $logger->infoRecords);
        self::assertCount(1, $logger->errorRecords);
        self::assertSame(
            'Erro ao criar pedido.',
            $logger->errorRecords[0]['message']
        );
    }
}
