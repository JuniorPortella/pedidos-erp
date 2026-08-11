<?php

declare(strict_types=1);

namespace App\Http;

use App\Routing\Router;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class Application
{
    public function __construct(
        private Router $router,
        private ErrorHandler $errorHandler,
        private LoggerInterface $logger
    ) {
    }

    public function handle(Request $request): Response
    {
        $startedAt = hrtime(true);

        try {
            $response = $this->router->dispatch(
                $request
            );
        } catch (Throwable $exception) {
            $response = $this->errorHandler->handle(
                $exception,
                $request
            );
        }

        $durationInMilliseconds = round(
            (hrtime(true) - $startedAt) / 1_000_000,
            3
        );

        $this->logger->info(
            'Requisicao HTTP concluida.',
            [
                'method' => $request->method,
                'path' => $request->path,
                'status' => $response->status(),
                'duration_ms' => $durationInMilliseconds,
            ]
        );

        return $response;
    }
}
