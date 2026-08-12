<?php

declare(strict_types=1);

namespace Refatoracao\Http;

use Refatoracao\Exception\OrderCreationException;
use Refatoracao\Exception\ValidationException;
use Refatoracao\Service\CreateOrderService;

final readonly class CreateOrderHandler
{
    public function __construct(private CreateOrderService $service)
    {
    }

    /**
     * @param array<string, mixed> $form
     */
    public function handle(string $method, array $form): Response
    {
        if (strtoupper($method) !== 'POST') {
            return new Response(
                status: 405,
                data: ['error' => 'Metodo HTTP nao permitido.'],
                headers: ['Allow' => 'POST']
            );
        }

        $customerName = $form['cliente_nome'] ?? '';

        try {
            $order = $this->service->create(
                is_string($customerName) ? $customerName : ''
            );

            return new Response(
                status: 201,
                data: [
                    'message' => 'Pedido criado com sucesso.',
                    'order' => [
                        'id' => $order->id,
                        'cliente_nome' => $order->customerName,
                    ],
                ]
            );
        } catch (ValidationException $exception) {
            return new Response(
                status: 422,
                data: ['error' => $exception->getMessage()]
            );
        } catch (OrderCreationException) {
            return new Response(
                status: 500,
                data: ['error' => 'Erro interno do servidor.']
            );
        }
    }
}
