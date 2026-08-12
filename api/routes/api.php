<?php

declare(strict_types=1);

use App\Controller\AuthenticationController;
use App\Http\Request;
use App\Http\Response;
use App\Routing\Router;

return static function (
    Router $router,
    AuthenticationController $authentication
): void {
    $healthHandler = static function (
        Request $request,
        array $parameters
    ): Response {
        return Response::json([
            'service' => 'pedidos-api',
            'status' => 'ok',
        ]);
    };

    $router->get('/', $healthHandler);
    $router->get('/health', $healthHandler);

    $router->post(
        '/auth/login',
        static fn (
            Request $request,
            array $parameters
        ): Response => $authentication->login($request)
    );

    $router->post(
        '/auth/refresh',
        static fn (
            Request $request,
            array $parameters
        ): Response => $authentication->refresh($request)
    );

    $router->post(
        '/auth/logout',
        static fn (
            Request $request,
            array $parameters
        ): Response => $authentication->logout($request)
    );
};
