<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Config\CorsConfig;
use App\Exception\ForbiddenException;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\CorsMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CorsMiddlewareTest extends TestCase
{
    private const ORIGIN = 'http://localhost:5173';

    private CorsMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new CorsMiddleware(
            new CorsConfig(self::ORIGIN)
        );
    }

    public function testAddsHeadersForAllowedOrigin(): void
    {
        $nextCalled = false;

        $response = $this->middleware->handle(
            $this->request('GET'),
            static function () use (&$nextCalled): Response {
                $nextCalled = true;

                return Response::json(['status' => 'ok']);
            }
        );

        self::assertTrue($nextCalled);
        self::assertSame(200, $response->status());
        self::assertSame(
            [self::ORIGIN],
            $response->headerValues(
                'Access-Control-Allow-Origin'
            )
        );
        self::assertSame(
            ['true'],
            $response->headerValues(
                'Access-Control-Allow-Credentials'
            )
        );
        self::assertSame(
            ['Origin'],
            $response->headerValues('Vary')
        );
    }

    public function testDoesNotAddHeadersWithoutOrigin(): void
    {
        $response = $this->middleware->handle(
            new Request('GET', '/health'),
            static fn (): Response =>
                Response::json(['status' => 'ok'])
        );

        self::assertSame(
            [],
            $response->headerValues(
                'Access-Control-Allow-Origin'
            )
        );
    }

    public function testAnswersValidPreflightWithoutCallingNext(): void
    {
        $response = $this->middleware->handle(
            $this->request(
                'OPTIONS',
                [
                    'access-control-request-method' => 'POST',
                    'access-control-request-headers' =>
                        'Content-Type, X-CSRF-Token',
                ]
            ),
            static function (): Response {
                self::fail(
                    'O router nao deve executar no preflight.'
                );
            }
        );

        self::assertSame(204, $response->status());
        self::assertSame('', $response->body());
        self::assertSame(
            ['GET, POST, PUT, DELETE, OPTIONS'],
            $response->headerValues(
                'Access-Control-Allow-Methods'
            )
        );
        self::assertSame(
            ['Content-Type, X-CSRF-Token'],
            $response->headerValues(
                'Access-Control-Allow-Headers'
            )
        );
        self::assertSame(
            ['600'],
            $response->headerValues(
                'Access-Control-Max-Age'
            )
        );
    }

    public function testRejectsUntrustedOrigin(): void
    {
        $this->expectException(ForbiddenException::class);

        $this->middleware->handle(
            new Request(
                'GET',
                '/health',
                headers: [
                    'origin' => 'https://malicioso.example',
                ]
            ),
            static fn (): Response => Response::empty()
        );
    }

    #[DataProvider('invalidPreflights')]
    public function testRejectsInvalidPreflight(
        string $method,
        ?string $headers
    ): void {
        $requestHeaders = [
            'access-control-request-method' => $method,
        ];

        if ($headers !== null) {
            $requestHeaders[
                'access-control-request-headers'
            ] = $headers;
        }

        $this->expectException(ForbiddenException::class);

        $this->middleware->handle(
            $this->request('OPTIONS', $requestHeaders),
            static fn (): Response => Response::empty()
        );
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function invalidPreflights(): array
    {
        return [
            'method' => ['PATCH', null],
            'authorization header' => [
                'GET',
                'Authorization',
            ],
            'empty header' => ['POST', 'Content-Type, '],
        ];
    }

    public function testAddsCorsHeadersToErrorForAllowedOrigin(): void
    {
        $response = $this->middleware->addHeadersToError(
            $this->request('GET'),
            Response::json(['error' => 'Falha'], 500)
        );

        self::assertSame(
            [self::ORIGIN],
            $response->headerValues(
                'Access-Control-Allow-Origin'
            )
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(
        string $method,
        array $headers = []
    ): Request {
        return new Request(
            $method,
            '/pedidos',
            headers: array_merge(
                ['origin' => self::ORIGIN],
                $headers
            )
        );
    }
}
