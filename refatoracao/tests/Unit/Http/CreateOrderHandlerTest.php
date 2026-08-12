<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Refatoracao\Http\CreateOrderHandler;
use Refatoracao\Service\CreateOrderService;
use Tests\Support\ArrayLogger;
use Tests\Support\InMemoryOrderRepository;

final class CreateOrderHandlerTest extends TestCase
{
    public function testReturnsCreatedOrder(): void
    {
        $response = $this->handler()->handle(
            'POST',
            ['cliente_nome' => 'Cliente Teste']
        );

        self::assertSame(201, $response->status);
        self::assertSame(
            'Pedido criado com sucesso.',
            $response->data['message']
        );
        self::assertSame(
            'Cliente Teste',
            $response->data['order']['cliente_nome']
        );
    }

    public function testReturnsValidationError(): void
    {
        $response = $this->handler()->handle(
            'POST',
            ['cliente_nome' => '']
        );

        self::assertSame(422, $response->status);
        self::assertSame(
            'O nome do cliente e obrigatorio.',
            $response->data['error']
        );
    }

    public function testRejectsOtherHttpMethods(): void
    {
        $response = $this->handler()->handle('GET', []);

        self::assertSame(405, $response->status);
        self::assertSame('POST', $response->headers['Allow']);
    }

    public function testHidesInternalDatabaseError(): void
    {
        $repository = new InMemoryOrderRepository();
        $repository->mustFail = true;

        $response = $this->handler($repository)->handle(
            'POST',
            ['cliente_nome' => 'Cliente Teste']
        );

        self::assertSame(500, $response->status);
        self::assertSame(
            'Erro interno do servidor.',
            $response->data['error']
        );
        self::assertStringNotContainsString(
            'Falha simulada',
            $response->data['error']
        );
    }

    private function handler(
        ?InMemoryOrderRepository $repository = null
    ): CreateOrderHandler {
        return new CreateOrderHandler(
            new CreateOrderService(
                $repository ?? new InMemoryOrderRepository(),
                new ArrayLogger()
            )
        );
    }
}
