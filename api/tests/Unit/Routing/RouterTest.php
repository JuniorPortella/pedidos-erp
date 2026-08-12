<?php

declare(strict_types=1);

namespace Tests\Unit\Routing;

use App\Exception\MethodNotAllowedException;
use App\Exception\RouteNotFoundException;
use App\Http\Request;
use App\Http\Response;
use App\Routing\Router;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testDispatchesStaticRoute(): void
    {
        $router = new Router();

        $router->get(
            '/health',
            static function (
                Request $request,
                array $parameters
            ): Response {
                return Response::json([
                    'status' => 'ok',
                ]);
            }
        );

        $response = $router->dispatch(
            new Request('GET', '/health')
        );

        self::assertSame(200, $response->status());
        self::assertSame(
            ['status' => 'ok'],
            json_decode(
                $response->body(),
                true,
                512,
                JSON_THROW_ON_ERROR
            )
        );
    }

    public function testDispatchesDynamicRoute(): void
    {
        $router = new Router();

        $router->get(
            '/pedidos/{id}',
            static function (
                Request $request,
                array $parameters
            ): Response {
                return Response::json([
                    'id' => $parameters['id'],
                ]);
            }
        );

        $response = $router->dispatch(
            new Request('GET', '/pedidos/42')
        );

        self::assertSame(
            ['id' => '42'],
            json_decode(
                $response->body(),
                true,
                512,
                JSON_THROW_ON_ERROR
            )
        );
    }

    public function testDecodesRouteParameter(): void
    {
        $router = new Router();

        $router->get(
            '/clientes/{nome}',
            static fn (
                Request $request,
                array $parameters
            ): Response => Response::json([
                'nome' => $parameters['nome'],
            ])
        );

        $response = $router->dispatch(
            new Request('GET', '/clientes/Empresa%20Teste')
        );

        self::assertSame(
            ['nome' => 'Empresa Teste'],
            json_decode(
                $response->body(),
                true,
                512,
                JSON_THROW_ON_ERROR
            )
        );
    }

    public function testAcceptsTrailingSlash(): void
    {
        $router = new Router();

        $router->get(
            '/pedidos',
            static fn (): Response =>
                Response::json(['items' => []])
        );

        $response = $router->dispatch(
            new Request('GET', '/pedidos/')
        );

        self::assertSame(200, $response->status());
    }

    public function testDispatchesDeleteRoute(): void
    {
        $router = new Router();

        $router->delete(
            '/usuarios/{id}',
            static fn (
                Request $request,
                array $parameters
            ): Response => Response::json([
                'id' => $parameters['id'],
            ])
        );

        $response = $router->dispatch(
            new Request('DELETE', '/usuarios/42')
        );

        self::assertSame(200, $response->status());
        self::assertSame(
            ['id' => '42'],
            json_decode(
                $response->body(),
                true,
                512,
                JSON_THROW_ON_ERROR
            )
        );
    }

    public function testThrowsWhenRouteDoesNotExist(): void
    {
        $router = new Router();

        $this->expectException(
            RouteNotFoundException::class
        );
        $this->expectExceptionMessage(
            'Rota nao encontrada.'
        );

        $router->dispatch(
            new Request('GET', '/nao-existe')
        );
    }

    public function testReportsAllowedMethods(): void
    {
        $router = new Router();

        $handler = static fn (): Response =>
            Response::json(['status' => 'ok']);

        $router->post('/pedidos', $handler);
        $router->get('/pedidos', $handler);

        try {
            $router->dispatch(
                new Request('PUT', '/pedidos')
            );

            self::fail(
                'O metodo PUT deveria ter sido rejeitado.'
            );
        } catch (MethodNotAllowedException $exception) {
            self::assertSame(
                ['GET', 'POST'],
                $exception->allowedMethods
            );

            self::assertSame(
                'Metodo HTTP nao permitido.',
                $exception->getMessage()
            );
        }
    }

    #[DataProvider('invalidRoutePaths')]
    public function testRejectsInvalidRoutePath(
        string $path
    ): void {
        $router = new Router();

        $this->expectException(
            InvalidArgumentException::class
        );

        $router->get(
            $path,
            static fn (): Response =>
                Response::empty()
        );
    }

    public static function invalidRoutePaths(): array
    {
        return [
            'without initial slash' => ['pedidos'],
            'invalid parameter name' => [
                '/pedidos/{1id}',
            ],
            'duplicated parameter' => [
                '/pedidos/{id}/itens/{id}',
            ],
        ];
    }

    public function testRejectsHandlerWithoutResponse(): void
    {
        $router = new Router();

        $router->get(
            '/invalida',
            static fn (): string => 'resposta invalida'
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'O handler da rota deve retornar Response.'
        );

        $router->dispatch(
            new Request('GET', '/invalida')
        );
    }
}
