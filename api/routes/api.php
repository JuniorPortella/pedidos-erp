<?php

declare(strict_types=1);

use App\Controller\AuthenticationController;
use App\Controller\ClientController;
use App\Controller\OrderController;
use App\Controller\UserController;
use App\Dto\AuthenticatedUser;
use App\Http\CsrfRequestValidator;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\AccessTokenMiddleware;
use App\Middleware\AdminAuthorization;
use App\Routing\Router;

return static function (
    Router $router,
    AuthenticationController $authentication,
    UserController $users,
    ClientController $clients,
    OrderController $orders,
    AccessTokenMiddleware $accessToken,
    AdminAuthorization $adminAuthorization,
    CsrfRequestValidator $csrf
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

    $router->post(
        '/auth/register',
        static fn (
            Request $request,
            array $parameters
        ): Response => $accessToken->handle(
            $request,
            static function (
                AuthenticatedUser $authenticatedUser
            ) use (
                $request,
                $csrf,
                $adminAuthorization,
                $users
            ): Response {
                $csrf->validate(
                    $request,
                    $authenticatedUser->token->csrfHash
                );

                $adminAuthorization->authorize(
                    $authenticatedUser
                );

                return $users->create($request);
            }
        )
    );

    $router->put(
        '/usuarios/{id}',
        static fn (
            Request $request,
            array $parameters
        ): Response => $accessToken->handle(
            $request,
            static function (
                AuthenticatedUser $authenticatedUser
            ) use (
                $request,
                $parameters,
                $csrf,
                $adminAuthorization,
                $users
            ): Response {
                $csrf->validate(
                    $request,
                    $authenticatedUser->token->csrfHash
                );

                $adminAuthorization->authorize(
                    $authenticatedUser
                );

                return $users->update(
                    $request,
                    $parameters['id'],
                    $authenticatedUser
                );
            }
        )
    );

    $router->delete(
        '/usuarios/{id}',
        static fn (
            Request $request,
            array $parameters
        ): Response => $accessToken->handle(
            $request,
            static function (
                AuthenticatedUser $authenticatedUser
            ) use (
                $request,
                $parameters,
                $csrf,
                $adminAuthorization,
                $users
            ): Response {
                $csrf->validate(
                    $request,
                    $authenticatedUser->token->csrfHash
                );

                $adminAuthorization->authorize(
                    $authenticatedUser
                );

                return $users->delete(
                    $parameters['id'],
                    $authenticatedUser
                );
            }
        )
    );

    $router->get(
        '/clientes',
        static fn (
            Request $request,
            array $parameters
        ): Response => $accessToken->handle(
            $request,
            static fn (
                AuthenticatedUser $authenticatedUser
            ): Response => $clients->index()
        )
    );

    $router->post(
        '/clientes',
        static fn (
            Request $request,
            array $parameters
        ): Response => $accessToken->handle(
            $request,
            static function (
                AuthenticatedUser $authenticatedUser
            ) use (
                $request,
                $csrf,
                $adminAuthorization,
                $clients
            ): Response {
                $csrf->validate(
                    $request,
                    $authenticatedUser->token->csrfHash
                );

                $adminAuthorization->authorize(
                    $authenticatedUser
                );

                return $clients->create($request);
            }
        )
    );

    $router->put(
        '/clientes/{id}',
        static fn (
            Request $request,
            array $parameters
        ): Response => $accessToken->handle(
            $request,
            static function (
                AuthenticatedUser $authenticatedUser
            ) use (
                $request,
                $parameters,
                $csrf,
                $adminAuthorization,
                $clients
            ): Response {
                $csrf->validate(
                    $request,
                    $authenticatedUser->token->csrfHash
                );

                $adminAuthorization->authorize(
                    $authenticatedUser
                );

                return $clients->update(
                    $request,
                    $parameters['id']
                );
            }
        )
    );

    $router->delete(
        '/clientes/{id}',
        static fn (
            Request $request,
            array $parameters
        ): Response => $accessToken->handle(
            $request,
            static function (
                AuthenticatedUser $authenticatedUser
            ) use (
                $request,
                $parameters,
                $csrf,
                $adminAuthorization,
                $clients
            ): Response {
                $csrf->validate(
                    $request,
                    $authenticatedUser->token->csrfHash
                );

                $adminAuthorization->authorize(
                    $authenticatedUser
                );

                return $clients->delete($parameters['id']);
            }
        )
    );

    $router->get(
        '/pedidos',
        static fn (
            Request $request,
            array $parameters
        ): Response => $accessToken->handle(
            $request,
            static fn (
                AuthenticatedUser $authenticatedUser
            ): Response => $orders->index()
        )
    );

    $router->get(
        '/pedidos/{id}',
        static fn (
            Request $request,
            array $parameters
        ): Response => $accessToken->handle(
            $request,
            static fn (
                AuthenticatedUser $authenticatedUser
            ): Response => $orders->show(
                $parameters['id']
            )
        )
    );

    $router->post(
        '/pedidos',
        static fn (
            Request $request,
            array $parameters
        ): Response => $accessToken->handle(
            $request,
            static function (
                AuthenticatedUser $authenticatedUser
            ) use (
                $request,
                $csrf,
                $orders
            ): Response {
                $csrf->validate(
                    $request,
                    $authenticatedUser->token->csrfHash
                );

                return $orders->create(
                    $request,
                    $authenticatedUser
                );
            }
        )
    );

    $router->put(
        '/pedidos/{id}',
        static fn (
            Request $request,
            array $parameters
        ): Response => $accessToken->handle(
            $request,
            static function (
                AuthenticatedUser $authenticatedUser
            ) use (
                $request,
                $parameters,
                $csrf,
                $orders
            ): Response {
                $csrf->validate(
                    $request,
                    $authenticatedUser->token->csrfHash
                );

                return $orders->update(
                    $request,
                    $parameters['id']
                );
            }
        )
    );
};
