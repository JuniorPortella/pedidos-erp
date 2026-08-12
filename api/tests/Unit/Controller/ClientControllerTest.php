<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\ClientController;
use App\Exception\ValidationException;
use App\Http\Request;
use App\Service\ClientInputValidator;
use App\Service\ClientService;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryClientRepository;

final class ClientControllerTest extends TestCase
{
    private ClientController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ClientController(
            new ClientService(new InMemoryClientRepository()),
            new ClientInputValidator()
        );
    }

    public function testCreatesListsUpdatesAndDeletesClient(): void
    {
        $createdResponse = $this->controller->create(
            $this->request([
                'nome' => 'Cliente Teste',
                'telefone' => '(11) 99999-9999',
            ])
        );

        self::assertSame(201, $createdResponse->status());
        $created = $this->decode($createdResponse->body())['client'];
        self::assertSame('Cliente Teste', $created['nome']);

        $list = $this->decode($this->controller->index()->body());
        self::assertCount(1, $list['clients']);

        $updatedResponse = $this->controller->update(
            $this->request([
                'nome' => 'Cliente Atualizado',
                'telefone' => '(11) 98888-8888',
            ], 'PUT'),
            (string) $created['id']
        );

        self::assertSame(
            'Cliente Atualizado',
            $this->decode($updatedResponse->body())['client']['nome']
        );

        self::assertSame(
            204,
            $this->controller->delete(
                (string) $created['id']
            )->status()
        );
        self::assertSame(
            [],
            $this->decode($this->controller->index()->body())['clients']
        );
    }

    public function testRejectsInvalidClientId(): void
    {
        $this->expectException(ValidationException::class);

        $this->controller->delete('invalido');
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
            '/clientes',
            headers: ['content-type' => 'application/json'],
            body: json_encode($body, JSON_THROW_ON_ERROR)
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
