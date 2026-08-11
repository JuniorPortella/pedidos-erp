<?php

declare(strict_types=1);

use App\Http\Application;
use App\Http\ErrorHandler;
use App\Logging\LoggerFactory;
use App\Routing\Router;

$logger = LoggerFactory::create();
$router = new Router();

$registerRoutes = require dirname(__DIR__)
    . '/routes/api.php';

if (!is_callable($registerRoutes)) {
    throw new LogicException(
        'O arquivo de rotas deve retornar uma funcao.'
    );
}

$registerRoutes($router);

return new Application(
    router: $router,
    errorHandler: new ErrorHandler($logger),
    logger: $logger
);
