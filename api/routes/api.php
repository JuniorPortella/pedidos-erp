<?php

declare(strict_types=1);

use App\Http\Request;
use App\Http\Response;
use App\Routing\Router;

return static function (Router $router): void {
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
};
