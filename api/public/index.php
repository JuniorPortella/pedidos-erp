<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);

if ($path === '/' || $path === '/health') {
    http_response_code(200);

    echo json_encode(
        [
            'service' => 'pedidos-api',
            'status' => 'ok',
        ],
        JSON_THROW_ON_ERROR
    );

    exit;
}

http_response_code(404);

echo json_encode(
    [
        'error' => 'Rota nao encontrada.',
    ],
    JSON_THROW_ON_ERROR
);
