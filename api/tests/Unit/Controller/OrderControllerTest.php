<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\OrderController;
use App\Dto\AuthenticatedUser;
use App\Dto\TokenClaims;
use App\Entity\OrderStatus;
use App\Entity\TokenType;
use App\Entity\User;
use App\Entity\UserProfile;
use App\Exception\ValidationException;
use App\Http\Request;
use App\Service\OrderInputValidator;
use App\Service\OrderService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryOrderRepository;

final class OrderControllerTest extends TestCase
{
    private InMemoryOrderRepository $repository;
    private OrderController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new InMemoryOrderRepository();
        $this->controller = new OrderController(
            new OrderService($this->repository),
            new OrderInputValidator()
        );
    }

    public function testCreatesListsAndShowsOrder(): void
    {
        $createdResponse = $this->controller->create(
            $this->request([
                'cliente_nome' => 'Cliente Teste',
                'descricao' => 'Pedido Teste',
                'status' => 'PENDENTE',
            ]),
            $this->authenticatedUser(7)
        );

        self::assertSame(201, $createdResponse->status());

        $createdBody = $this->decode($createdResponse->body());
        $orderId = $createdBody['order']['id'];

        self::assertSame(
            'Cliente Teste',
            $createdBody['order']['cliente_nome']
        );
        self::assertSame(7, $createdBody['order']['criado_por']);

        $listBody = $this->decode(
            $this->controller->index()->body()
        );

        self::assertCount(1, $listBody['orders']);

        $showBody = $this->decode(
            $this->controller->show(
                (string) $orderId
            )->body()
        );

        self::assertSame(
            $orderId,
            $showBody['order']['id']
        );
    }

    public function testUpdatesOrder(): void
    {
        $order = $this->repository->create(
            'Cliente',
            'Descricao',
            OrderStatus::Pending,
            7
        );

        $response = $this->controller->update(
            $this->request([
                'cliente_nome' => 'Cliente Atualizado',
                'descricao' => 'Descricao Atualizada',
                'status' => 'CONCLUIDO',
            ], 'PUT'),
            (string) $order->id
        );

        self::assertSame(200, $response->status());

        $body = $this->decode($response->body());

        self::assertSame(
            'CONCLUIDO',
            $body['order']['status']
        );
        self::assertSame(7, $body['order']['criado_por']);
    }

    public function testRejectsInvalidOrderId(): void
    {
        $this->expectException(ValidationException::class);

        $this->controller->show('invalido');
    }

    /**
     * @param array<string, mixed> $body
     */
    private function request(
        array $body,
        string $method = 'POST'
    ): Request {
        return new Request(
            $method,
            '/pedidos',
            body: json_encode($body, JSON_THROW_ON_ERROR)
        );
    }

    private function authenticatedUser(
        int $id
    ): AuthenticatedUser {
        $now = new DateTimeImmutable();

        return new AuthenticatedUser(
            new User(
                $id,
                'Operador',
                'operador@example.com',
                'operador',
                UserProfile::Operator,
                true,
                $now,
                $now
            ),
            new TokenClaims(
                $id,
                'token-id',
                TokenType::Access,
                time(),
                time(),
                time() + 900
            )
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $body): array
    {
        return json_decode(
            $body,
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }
}
