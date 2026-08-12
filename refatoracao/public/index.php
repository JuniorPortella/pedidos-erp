<?php

declare(strict_types=1);

use Refatoracao\Database\ConnectionFactory;
use Refatoracao\Http\CreateOrderHandler;
use Refatoracao\Http\Response;
use Refatoracao\Logging\JsonStreamLogger;
use Refatoracao\Repository\PdoOrderRepository;
use Refatoracao\Service\CreateOrderService;

require dirname(__DIR__) . '/vendor/autoload.php';

$logger = new JsonStreamLogger();

try {
    $handler = new CreateOrderHandler(
        new CreateOrderService(
            new PdoOrderRepository(
                ConnectionFactory::fromEnvironment()
            ),
            $logger
        )
    );

    $response = $handler->handle(
        $_SERVER['REQUEST_METHOD'] ?? 'GET',
        $_POST
    );
} catch (Throwable $exception) {
    $logger->error(
        'Erro nao tratado na aplicacao refatorada.',
        ['exception' => $exception::class]
    );

    $response = new Response(
        status: 500,
        data: ['error' => 'Erro interno do servidor.']
    );
}

$response->emit();
