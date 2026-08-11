<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Application;
use App\Http\ErrorHandler;
use App\Http\Request;
use App\Http\Response;
use App\Routing\Router;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ApplicationTest extends TestCase
{
    public function testHandlesSuccessfulRequest(): void
    {
        $router = new Router();

        $router->get(
            '/health',
            static fn (): Response =>
                Response::json([
                    'status' => 'ok',
                ])
        );

        [$application, $logs] =
            $this->createApplication($router);

        $response = $application->handle(
            new Request('GET', '/health')
        );

        self::assertSame(200, $response->status());

        self::assertSame(
            ['status' => 'ok'],
            $this->decode($response)
        );

        $records = $logs->getRecords();

        self::assertCount(1, $records);
        self::assertSame(
            'Requisicao HTTP concluida.',
            $records[0]->message
        );
        self::assertSame(
            'GET',
            $records[0]->context['method']
        );
        self::assertSame(
            '/health',
            $records[0]->context['path']
        );
        self::assertSame(
            200,
            $records[0]->context['status']
        );
        self::assertGreaterThanOrEqual(
            0,
            $records[0]->context['duration_ms']
        );
    }

    public function testConvertsRouteErrorIntoResponse(): void
    {
        [$application, $logs] =
            $this->createApplication(new Router());

        $response = $application->handle(
            new Request('GET', '/nao-existe')
        );

        self::assertSame(404, $response->status());

        self::assertSame(
            ['error' => 'Rota nao encontrada.'],
            $this->decode($response)
        );

        $records = $logs->getRecords();

        self::assertCount(1, $records);
        self::assertSame(
            404,
            $records[0]->context['status']
        );
    }

    public function testConvertsAndLogsUnexpectedError(): void
    {
        $router = new Router();

        $router->get(
            '/erro',
            static function (): Response {
                throw new RuntimeException(
                    'Detalhe interno sensivel.'
                );
            }
        );

        [$application, $logs] =
            $this->createApplication($router);

        $response = $application->handle(
            new Request('GET', '/erro')
        );

        self::assertSame(500, $response->status());

        self::assertSame(
            [
                'error' => 'Erro interno do servidor.',
            ],
            $this->decode($response)
        );

        $records = $logs->getRecords();

        self::assertCount(2, $records);

        self::assertSame(
            'Erro nao tratado durante a requisicao.',
            $records[0]->message
        );

        self::assertSame(
            'Requisicao HTTP concluida.',
            $records[1]->message
        );

        self::assertSame(
            500,
            $records[1]->context['status']
        );
    }

    /**
     * @return array{Application, TestHandler}
     */
    private function createApplication(
        Router $router
    ): array {
        $logHandler = new TestHandler();

        $logger = new Logger('test');
        $logger->pushHandler($logHandler);

        $errorHandler = new ErrorHandler($logger);

        return [
            new Application(
                $router,
                $errorHandler,
                $logger
            ),
            $logHandler,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        return json_decode(
            $response->body(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }
}
