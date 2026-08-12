<?php

declare(strict_types=1);

use App\Controller\AuthenticationController;
use App\Controller\UserController;
use App\Dto\AuthenticatedUser;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\AccessTokenMiddleware;
use App\Middleware\AdminAuthorization;
use App\Routing\Router;

return static function (
    Router $router,
    AuthenticationController $authentication,
    UserController $users,
    AccessTokenMiddleware $accessToken,
    AdminAuthorization $adminAuthorization
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

    $router->get(
        '/auth/me',
        static fn (
            Request $request,
            array $parameters
        ): Response => $accessToken->handle(
            $request,
            static fn (
                AuthenticatedUser $authenticatedUser
            ): Response => $authentication->me(
                $authenticatedUser
            )
        )
    );

    $router->get(
        '/usuarios',
        static fn (
            Request $request,
            array $parameters
        ): Response => $accessToken->handle(
            $request,
            static function (
                AuthenticatedUser $authenticatedUser
            ) use (
                $adminAuthorization,
                $users
            ): Response {
                $adminAuthorization->authorize(
                    $authenticatedUser
                );

                return $users->index();
            }
        )
    );
};
