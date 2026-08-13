<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\AuthenticatedUser;
use App\Entity\Order;
use App\Exception\ValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Service\OrderInputValidator;
use App\Service\OrderService;

final readonly class OrderController
{
    public function __construct(
        private OrderService $orders,
        private OrderInputValidator $validator
    ) {
    }

    public function index(): Response
    {
        return Response::json([
            'orders' => array_map(
                $this->orderData(...),
                $this->orders->findAll()
            ),
        ]);
    }

    public function show(string $id): Response
    {
        return Response::json([
            'order' => $this->orderData(
                $this->orders->findById(
                    $this->orderId($id)
                )
            ),
        ]);
    }

    public function create(
        Request $request,
        AuthenticatedUser $authenticatedUser
    ): Response {
        $order = $this->orders->create(
            $this->validator->validate($request->json()),
            $authenticatedUser->user->id
        );

        return Response::json(
            ['order' => $this->orderData($order)],
            201
        );
    }

    public function update(
        Request $request,
        string $id
    ): Response {
        $order = $this->orders->update(
            $this->orderId($id),
            $this->validator->validate($request->json())
        );

        return Response::json([
            'order' => $this->orderData($order),
        ]);
    }

    /**
     * @return array<string, int|string>
     */
    private function orderData(Order $order): array
    {
        return [
            'id' => $order->id,
            'cliente_id' => $order->clientId,
            'descricao' => $order->description,
            'valor_total' => $order->totalAmount,
            'status' => $order->status->value,
            'criado_por' => $order->createdBy,
            'created_at' => $order->createdAt->format(
                DATE_ATOM
            ),
            'updated_at' => $order->updatedAt->format(
                DATE_ATOM
            ),
        ];
    }

    private function orderId(string $value): int
    {
        if (
            preg_match('/\A[1-9][0-9]*\z/', $value) !== 1
        ) {
            throw new ValidationException([
                'id' => 'Identificador de pedido invalido.',
            ]);
        }

        return (int) $value;
    }
}
