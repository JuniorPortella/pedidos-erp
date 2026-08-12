<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Config\CorsConfig;
use App\Exception\ForbiddenException;
use App\Http\Request;
use App\Http\Response;

final readonly class CorsMiddleware
{
    private const ALLOWED_METHODS = [
        'GET',
        'POST',
        'PUT',
        'DELETE',
        'OPTIONS',
    ];

    private const ALLOWED_HEADERS = [
        'content-type',
        'x-csrf-token',
    ];

    public function __construct(
        private CorsConfig $config
    ) {
    }

    /**
     * @param callable(): Response $next
     */
    public function handle(
        Request $request,
        callable $next
    ): Response {
        $origin = $request->header('Origin');

        if ($origin === null) {
            return $next();
        }

        $this->assertAllowedOrigin($origin);

        if ($this->isPreflight($request)) {
            $this->validatePreflight($request);

            return $this->addPreflightHeaders(
                Response::empty(),
                $origin
            );
        }

        return $this->addSimpleHeaders(
            $next(),
            $origin
        );
    }

    public function addHeadersToError(
        Request $request,
        Response $response
    ): Response {
        $origin = $request->header('Origin');

        if (
            $origin === null
            || !hash_equals(
                $this->config->allowedOrigin,
                $origin
            )
        ) {
            return $response;
        }

        return $this->addSimpleHeaders(
            $response,
            $origin
        );
    }

    private function isPreflight(Request $request): bool
    {
        return $request->method === 'OPTIONS'
            && $request->header(
                'Access-Control-Request-Method'
            ) !== null;
    }

    private function assertAllowedOrigin(
        string $origin
    ): void {
        if (
            !hash_equals(
                $this->config->allowedOrigin,
                $origin
            )
        ) {
            throw new ForbiddenException(
                'Origem nao permitida.'
            );
        }
    }

    private function validatePreflight(
        Request $request
    ): void {
        $requestedMethod = strtoupper(
            trim(
                (string) $request->header(
                    'Access-Control-Request-Method'
                )
            )
        );

        if (
            !in_array(
                $requestedMethod,
                self::ALLOWED_METHODS,
                true
            )
        ) {
            throw new ForbiddenException(
                'Metodo CORS nao permitido.'
            );
        }

        $requestedHeaders = $request->header(
            'Access-Control-Request-Headers'
        );

        if ($requestedHeaders === null) {
            return;
        }

        foreach (explode(',', $requestedHeaders) as $header) {
            $header = strtolower(trim($header));

            if (
                $header === ''
                || !in_array(
                    $header,
                    self::ALLOWED_HEADERS,
                    true
                )
            ) {
                throw new ForbiddenException(
                    'Header CORS nao permitido.'
                );
            }
        }
    }

    private function addSimpleHeaders(
        Response $response,
        string $origin
    ): Response {
        return $response
            ->withHeader(
                'Access-Control-Allow-Origin',
                $origin
            )
            ->withHeader(
                'Access-Control-Allow-Credentials',
                'true'
            )
            ->withHeader('Vary', 'Origin');
    }

    private function addPreflightHeaders(
        Response $response,
        string $origin
    ): Response {
        return $this->addSimpleHeaders(
            $response,
            $origin
        )
            ->withHeader(
                'Access-Control-Allow-Methods',
                implode(', ', self::ALLOWED_METHODS)
            )
            ->withHeader(
                'Access-Control-Allow-Headers',
                'Content-Type, X-CSRF-Token'
            )
            ->withHeader('Access-Control-Max-Age', '600')
            ->withHeader(
                'Vary',
                'Origin, Access-Control-Request-Method, Access-Control-Request-Headers'
            );
    }
}
