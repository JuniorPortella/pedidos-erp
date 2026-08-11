<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Exception\InvalidJsonBodyException;
use App\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testCreatesRequestFromServerData(): void
    {
        $request = Request::fromServer(
            server: [
                'REQUEST_METHOD' => 'post',
                'REQUEST_URI' => '/pedidos?page=2',
                'HTTP_AUTHORIZATION' => 'Bearer token',
                'HTTP_X_CSRF_TOKEN' => 'csrf-token',
                'CONTENT_TYPE' => 'application/json',
            ],
            query: [
                'page' => '2',
            ],
            cookies: [
                'refresh_token' => 'refresh-jwt',
            ],
            body: json_encode(
                [
                    'cliente_nome' => 'Empresa Teste',
                    'descricao' => 'Pedido de teste',
                ],
                JSON_THROW_ON_ERROR
            )
        );

        self::assertSame('POST', $request->method);
        self::assertSame('/pedidos', $request->path);
        self::assertSame('2', $request->query['page']);

        self::assertSame(
            'Bearer token',
            $request->header('Authorization')
        );

        self::assertSame(
            'csrf-token',
            $request->header('X-CSRF-Token')
        );

        self::assertSame(
            'application/json',
            $request->header('Content-Type')
        );

        self::assertSame(
            'refresh-jwt',
            $request->cookie('refresh_token')
        );

        self::assertSame(
            [
                'cliente_nome' => 'Empresa Teste',
                'descricao' => 'Pedido de teste',
            ],
            $request->json()
        );
    }

    public function testUsesDefaultsWhenServerDataIsMissing(): void
    {
        $request = Request::fromServer([]);

        self::assertSame('GET', $request->method);
        self::assertSame('/', $request->path);
        self::assertSame([], $request->query);
        self::assertSame([], $request->headers);
        self::assertSame([], $request->cookies);
    }

    public function testReturnsNullForMissingHeaderAndCookie(): void
    {
        $request = Request::fromServer([]);

        self::assertNull(
            $request->header('Authorization')
        );

        self::assertNull(
            $request->cookie('access_token')
        );
    }

    public function testReturnsEmptyArrayForEmptyBody(): void
    {
        $request = Request::fromServer(
            server: [],
            body: '   '
        );

        self::assertSame([], $request->json());
    }

    #[DataProvider('invalidJsonBodies')]
    public function testRejectsInvalidJsonBody(
        string $body
    ): void {
        $request = Request::fromServer(
            server: [],
            body: $body
        );

        $this->expectException(
            InvalidJsonBodyException::class
        );

        $request->json();
    }

    public static function invalidJsonBodies(): array
    {
        return [
            'malformed JSON' => ['{"nome":'],
            'JSON list' => ['["item"]'],
            'JSON string' => ['"texto"'],
            'JSON null' => ['null'],
        ];
    }
}
