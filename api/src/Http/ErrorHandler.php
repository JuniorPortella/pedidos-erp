<?php

declare(strict_types=1);

namespace App\Http;

use App\Exception\InvalidCredentialsException;
use App\Exception\InvalidCsrfTokenException;
use App\Exception\InvalidJsonBodyException;
use App\Exception\InvalidTokenException;
use App\Exception\ForbiddenException;
use App\Exception\MethodNotAllowedException;
use App\Exception\RefreshTokenNotActiveException;
use App\Exception\RefreshTokenReuseException;
use App\Exception\RouteNotFoundException;
use App\Exception\UnauthenticatedException;
use App\Exception\ValidationException;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class ErrorHandler
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function handle(
        Throwable $exception,
        ?Request $request = null
    ): Response {
        if ($exception instanceof InvalidJsonBodyException) {
            return Response::json(
                [
                    'error' => $exception->getMessage(),
                ],
                400
            );
        }

        if ($exception instanceof ValidationException) {
            return Response::json(
                [
                    'error' => $exception->getMessage(),
                    'fields' => $exception->errors(),
                ],
                422
            );
        }

        if (
            $exception instanceof InvalidCredentialsException
        ) {
            return Response::json(
                [
                    'error' => 'Usuario ou senha invalidos.',
                ],
                401
            );
        }

        if ($exception instanceof UnauthenticatedException) {
            return Response::json(
                [
                    'error' => 'Autenticacao necessaria.',
                ],
                401
            );
        }

        if ($exception instanceof ForbiddenException) {
            return Response::json(
                [
                    'error' => 'Acesso nao permitido.',
                ],
                403
            );
        }

        if ($exception instanceof InvalidCsrfTokenException) {
            return Response::json(
                [
                    'error' => 'Token CSRF invalido.',
                ],
                403
            );
        }

        if (
            $exception instanceof InvalidTokenException
            || $exception
                instanceof RefreshTokenNotActiveException
        ) {
            return Response::json(
                [
                    'error' => 'Token invalido ou expirado.',
                ],
                401
            );
        }

        if ($exception instanceof RefreshTokenReuseException) {
            $this->logger->warning(
                'Reutilizacao de refresh token detectada.',
                $this->requestContext($request)
            );

            return Response::json(
                [
                    'error' => 'Sessao invalida. Entre novamente.',
                ],
                401
            );
        }

        if ($exception instanceof RouteNotFoundException) {
            return Response::json(
                [
                    'error' => 'Rota nao encontrada.',
                ],
                404
            );
        }

        if ($exception instanceof MethodNotAllowedException) {
            return Response::json(
                [
                    'error' => 'Metodo HTTP nao permitido.',
                ],
                405
            )->withHeader(
                'Allow',
                implode(', ', $exception->allowedMethods)
            );
        }

        $this->logger->error(
            'Erro nao tratado durante a requisicao.',
            array_merge(
                $this->requestContext($request),
                [
                    'exception' => $exception,
                ]
            )
        );

        return Response::json(
            [
                'error' => 'Erro interno do servidor.',
            ],
            500
        );
    }

    /**
     * @return array<string, string>
     */
    private function requestContext(
        ?Request $request
    ): array {
        if ($request === null) {
            return [];
        }

        return [
            'method' => $request->method,
            'path' => $request->path,
        ];
    }
}
