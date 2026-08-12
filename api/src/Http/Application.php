<?php

declare(strict_types=1);

namespace App\Http;

use App\Middleware\CorsMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Routing\Router;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class Application
{
    public function __construct(
        private Router $router,
        private ErrorHandler $errorHandler,
        private LoggerInterface $logger,
        private ?CorsMiddleware $cors = null,
        private ?SecurityHeadersMiddleware $securityHeaders = null
    ) {
    }

    public function handle(Request $request): Response
    {
        $startedAt = hrtime(true);

        try {
            $dispatch = fn (): Response =>
                $this->router->dispatch($request);

            $response = $this->cors === null
                ? $dispatch()
                : $this->cors->handle(
                    $request,
                    $dispatch
                );
        } catch (Throwable $exception) {
            $response = $this->errorHandler->handle(
                $exception,
                $request
            );

            if ($this->cors !== null) {
                $response = $this->cors
                    ->addHeadersToError(
                        $request,
                        $response
                    );
            }
        }

        if ($this->securityHeaders !== null) {
            $response = $this->securityHeaders->add($response);
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
