<?php

declare(strict_types=1);

use App\Config\AuthConfig;
use App\Config\Environment;
use App\Database\ConnectionFactory;
use App\Database\MigrationRunner;
use App\Entity\TokenRevocationReason;
use App\Entity\UserProfile;
use App\Repository\PdoAuthenticationRepository;
use App\Repository\PdoClientRepository;
use App\Repository\PdoRefreshTokenRepository;
use App\Repository\PdoTokenBlacklistRepository;
use App\Repository\PdoUserRepository;
use App\Security\CsrfTokenService;
use App\Security\DataCipher;
use App\Security\LookupHasher;
use App\Service\AuthenticationService;
use App\Service\JwtService;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

try {
    $baseUrl = rtrim(
        getenv('SMOKE_BASE_URL') ?: 'http://localhost',
        '/'
    );

    $frontendOrigin = Environment::getRequired(
        'FRONTEND_ORIGIN'
    );

    [$healthStatus, $healthBody, $healthHeaders] = requestJson(
        $baseUrl . '/health'
    );

    assertSmoke(
        $healthStatus === 200,
        'GET /health deve responder HTTP 200.'
    );
    assertSmoke(
        ($healthBody['service'] ?? null) === 'pedidos-api'
            && ($healthBody['status'] ?? null) === 'ok',
        'GET /health retornou um corpo inesperado.'
    );
    assertSmoke(
        !isset($healthHeaders['set-cookie']),
        'GET /health nao deve criar cookies.'
    );
    assertSmoke(
        !isset(
            $healthHeaders['access-control-allow-origin']
        ),
        'Requisicao sem Origin nao deve receber headers CORS.'
    );
    assertSmoke(
        ($healthHeaders['x-content-type-options'] ?? null)
            === 'nosniff'
            && ($healthHeaders['x-frame-options'] ?? null)
                === 'DENY'
            && ($healthHeaders['cache-control'] ?? null)
                === 'no-store',
        'GET /health nao retornou os headers de seguranca.'
    );
    assertSmoke(
        !str_contains(
            strtolower($healthHeaders['server'] ?? ''),
            'apache/'
        )
            && !isset($healthHeaders['x-powered-by']),
        'A API revelou versoes do servidor ou do PHP.'
    );
    writeSuccess('API respondeu ao healthcheck');

    [
        $openApiStatus,
        $openApiBody,
        $openApiHeaders,
    ] = requestJson(
        $baseUrl . '/openapi.json'
    );

    assertSmoke(
        $openApiStatus === 200,
        'GET /openapi.json deve responder HTTP 200.'
    );
    assertSmoke(
        ($openApiBody['openapi'] ?? null) === '3.1.0'
            && ($openApiBody['info']['title'] ?? null)
                === 'PedidosFull API',
        'O contrato OpenAPI retornou metadados inesperados.'
    );
    assertSmoke(
        isset($openApiBody['paths']['/pedidos']['get'])
            && isset($openApiBody['paths']['/pedidos']['post'])
            && isset($openApiBody['paths']['/clientes']['get'])
            && isset($openApiBody['paths']['/clientes']['post']),
        'O contrato OpenAPI nao documentou as rotas principais.'
    );
    assertSmoke(
        str_starts_with(
            strtolower($openApiHeaders['content-type'] ?? ''),
            'application/json'
        ),
        'O contrato OpenAPI deve usar Content-Type application/json.'
    );
    assertSmoke(
        !isset($openApiHeaders['set-cookie']),
        'GET /openapi.json nao deve criar cookies.'
    );
    writeSuccess('Contrato OpenAPI esta publicado e acessivel');

    [$unsupportedMediaTypeStatus] = requestJson(
        $baseUrl . '/auth/login',
        'POST',
        ['Content-Type: text/plain'],
        rawBody: '{"usuario":"teste","senha":"Senha123!"}'
    );

    assertSmoke(
        $unsupportedMediaTypeStatus === 415,
        'JSON sem application/json deve responder HTTP 415.'
    );
    writeSuccess('API exigiu Content-Type JSON e ocultou versoes');

    [
        $corsStatus,
        ,
        $corsHeaders,
    ] = requestJson(
        $baseUrl . '/health',
        requestHeaders: [
            'Origin: ' . $frontendOrigin,
        ]
    );

    assertSmoke(
        $corsStatus === 200,
        'Origem permitida deve acessar GET /health.'
    );
    assertSmoke(
        ($corsHeaders['access-control-allow-origin'] ?? null)
            === $frontendOrigin,
        'CORS deve devolver somente a origem configurada.'
    );
    assertSmoke(
        ($corsHeaders['access-control-allow-credentials']
            ?? null) === 'true',
        'CORS deve permitir o envio de cookies.'
    );
    assertSmoke(
        ($corsHeaders['vary'] ?? null) === 'Origin',
        'Resposta CORS deve variar pela origem.'
    );
    assertSmoke(
        !isset($corsHeaders['set-cookie']),
        'GET /health com CORS nao deve criar cookies.'
    );
    writeSuccess('CORS permitiu somente a origem configurada');

    [
        $preflightStatus,
        $preflightBody,
        $preflightHeaders,
    ] = requestJson(
        $baseUrl . '/pedidos',
        'OPTIONS',
        [
            'Origin: ' . $frontendOrigin,
            'Access-Control-Request-Method: POST',
            'Access-Control-Request-Headers: Content-Type, X-CSRF-Token',
        ]
    );

    assertSmoke(
        $preflightStatus === 204
            && $preflightBody === [],
        'Preflight valido deve responder HTTP 204 sem corpo.'
    );
    assertSmoke(
        ($preflightHeaders['access-control-allow-methods']
            ?? null) === 'GET, POST, PUT, DELETE, OPTIONS',
        'Preflight deve informar os metodos permitidos.'
    );
    assertSmoke(
        ($preflightHeaders['access-control-allow-headers']
            ?? null) === 'Content-Type, X-CSRF-Token',
        'Preflight deve informar os headers permitidos.'
    );
    assertSmoke(
        ($preflightHeaders['access-control-max-age']
            ?? null) === '600',
        'Preflight deve informar o tempo de cache.'
    );
    assertSmoke(
        !isset($preflightHeaders['set-cookie']),
        'Preflight nao deve criar cookies.'
    );
    writeSuccess('CORS respondeu ao preflight sem autenticar');

    [
        $deniedOriginStatus,
        $deniedOriginBody,
        $deniedOriginHeaders,
    ] = requestJson(
        $baseUrl . '/health',
        requestHeaders: [
            'Origin: https://malicioso.example',
        ]
    );

    assertSmoke(
        $deniedOriginStatus === 403
            && isset($deniedOriginBody['error']),
        'Origem nao permitida deve responder HTTP 403.'
    );
    assertSmoke(
        !isset(
            $deniedOriginHeaders[
                'access-control-allow-origin'
            ]
        ),
        'Origem rejeitada nao deve receber permissao CORS.'
    );

    [$deniedHeaderStatus] = requestJson(
        $baseUrl . '/pedidos',
        'OPTIONS',
        [
            'Origin: ' . $frontendOrigin,
            'Access-Control-Request-Method: GET',
            'Access-Control-Request-Headers: Authorization',
        ]
    );

    assertSmoke(
        $deniedHeaderStatus === 403,
        'Header CORS nao permitido deve responder HTTP 403.'
    );
    writeSuccess('CORS bloqueou origem e headers nao permitidos');

    [
        $notFoundStatus,
        $notFoundBody,
        $notFoundHeaders,
    ] = requestJson(
        $baseUrl . '/rota-inexistente'
    );

    assertSmoke(
        $notFoundStatus === 404,
        'Uma rota inexistente deve responder HTTP 404.'
    );
    assertSmoke(
        isset($notFoundBody['error']),
        'A resposta 404 deve possuir uma mensagem de erro.'
    );
    assertSmoke(
        !isset($notFoundHeaders['set-cookie']),
        'A resposta 404 nao deve criar cookies.'
    );
    writeSuccess('API tratou uma rota inexistente');

    [
        $methodNotAllowedStatus,
        $methodNotAllowedBody,
        $methodNotAllowedHeaders,
    ] = requestJson(
        $baseUrl . '/health',
        'POST'
    );

    assertSmoke(
        $methodNotAllowedStatus === 405,
        'POST /health deve responder HTTP 405.'
    );
    assertSmoke(
        isset($methodNotAllowedBody['error']),
        'A resposta 405 deve possuir uma mensagem de erro.'
    );
    assertSmoke(
        ($methodNotAllowedHeaders['allow'] ?? null) === 'GET',
        'A resposta 405 deve informar Allow: GET.'
    );
    assertSmoke(
        !isset($methodNotAllowedHeaders['set-cookie']),
        'A resposta 405 nao deve criar cookies.'
    );
    writeSuccess('API rejeitou um metodo HTTP nao permitido');

    [
        $loginStatus,
        $loginBody,
        $loginHeaders,
    ] = requestJson(
        $baseUrl . '/auth/login',
        'POST'
    );

    assertSmoke(
        $loginStatus === 422,
        'POST /auth/login sem credenciais deve responder HTTP 422.'
    );
    assertSmoke(
        isset($loginBody['fields']['usuario'])
            && isset($loginBody['fields']['senha']),
        'Login vazio deve informar os campos invalidos.'
    );
    assertSmoke(
        !isset($loginHeaders['set-cookie']),
        'Login invalido nao deve criar cookies.'
    );
    writeSuccess('Rota de login validou as credenciais');

    [
        $refreshStatus,
        $refreshBody,
        $refreshHeaders,
    ] = requestJson(
        $baseUrl . '/auth/refresh',
        'POST'
    );

    assertSmoke(
        $refreshStatus === 403,
        'POST /auth/refresh sem CSRF deve responder HTTP 403.'
    );
    assertSmoke(
        ($refreshBody['error'] ?? null)
            === 'Token CSRF invalido.',
        'Refresh sem CSRF retornou uma mensagem inesperada.'
    );
    assertSmoke(
        !isset($refreshHeaders['set-cookie']),
        'Refresh sem CSRF nao deve alterar cookies.'
    );
    writeSuccess('Rota de refresh exigiu protecao CSRF');

    [
        $logoutStatus,
        $logoutBody,
        $logoutHeaders,
    ] = requestJson(
        $baseUrl . '/auth/logout',
        'POST'
    );

    assertSmoke(
        $logoutStatus === 403,
        'POST /auth/logout sem CSRF deve responder HTTP 403.'
    );
    assertSmoke(
        ($logoutBody['error'] ?? null)
            === 'Token CSRF invalido.',
        'Logout sem CSRF retornou uma mensagem inesperada.'
    );
    assertSmoke(
        !isset($logoutHeaders['set-cookie']),
        'Logout sem CSRF nao deve alterar cookies.'
    );
    writeSuccess('Rota de logout exigiu protecao CSRF');

    $connection = ConnectionFactory::create();
    $databaseVersion = $connection
        ->query('SELECT VERSION()')
        ->fetchColumn();

    assertSmoke(
        is_string($databaseVersion)
            && $databaseVersion !== '',
        'Nao foi possivel consultar a versao do MySQL.'
    );
    writeSuccess('Conexao PDO com o MySQL esta funcional');

    $migrationRunner = new MigrationRunner(
        $connection,
        dirname(__DIR__, 2) . '/database/migrations'
    );

    assertSmoke(
        $migrationRunner->run() === [],
        'Existiam migrations pendentes durante o smoke test.'
    );
    writeSuccess('Migrations estao aplicadas e sao idempotentes');

    $statement = $connection->prepare(
        <<<'SQL'
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = :database_name
        SQL
    );

    $statement->execute([
        'database_name' => Environment::getRequired(
            'DB_DATABASE'
        ),
    ]);

    $tables = $statement->fetchAll(PDO::FETCH_COLUMN);
    $requiredTables = [
        'clientes',
        'pedidos',
        'login_rate_limits',
        'refresh_tokens',
        'schema_migrations',
        'token_blacklist',
        'usuarios',
    ];

    foreach ($requiredTables as $table) {
        assertSmoke(
            in_array($table, $tables, true),
            sprintf(
                'A tabela obrigatoria nao existe: %s.',
                $table
            )
        );
    }

    writeSuccess('Schema minimo esta disponivel');

    verifyHttpAuthorization(
        $baseUrl,
        $connection
    );
    writeSuccess(
        'Rotas protegidas aplicaram autenticacao e perfis'
    );

    verifyLogoutSecurity($connection);
    writeSuccess('Logout revogou refresh e bloqueou access token');

    fwrite(STDOUT, 'Smoke test concluido.' . PHP_EOL);
    exit(0);
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        sprintf(
            '[FALHA] %s%s',
            $exception->getMessage(),
            PHP_EOL
        )
    );

    exit(1);
}

/**
 * @return array{
 *     0: int,
 *     1: array<string, mixed>,
 *     2: array<string, string>,
 *     3: list<string>
 * }
 * @param list<string> $requestHeaders
 * @param array<string, mixed>|null $jsonBody
 */
function requestJson(
    string $url,
    string $method = 'GET',
    array $requestHeaders = [],
    ?array $jsonBody = null,
    ?string $rawBody = null
): array
{
    $httpOptions = [
        'method' => $method,
        'ignore_errors' => true,
        'timeout' => 5,
    ];

    if ($jsonBody !== null) {
        $requestHeaders[] = 'Content-Type: application/json';
        $httpOptions['content'] = json_encode(
            $jsonBody,
            JSON_THROW_ON_ERROR
        );
    }

    if ($rawBody !== null) {
        if ($jsonBody !== null) {
            throw new RuntimeException(
                'Informe jsonBody ou rawBody, nunca ambos.'
            );
        }

        $httpOptions['content'] = $rawBody;
    }

    if ($requestHeaders !== []) {
        $httpOptions['header'] = implode(
            "\r\n",
            $requestHeaders
        );
    }

    $context = stream_context_create([
        'http' => $httpOptions,
    ]);

    $body = file_get_contents($url, false, $context);

    if ($body === false) {
        throw new RuntimeException(
            sprintf('Nao foi possivel acessar %s.', $url)
        );
    }

    $statusLine = $http_response_header[0] ?? '';

    if (
        preg_match(
            '/\AHTTP\/\S+\s+(\d{3})\b/',
            $statusLine,
            $matches
        ) !== 1
    ) {
        throw new RuntimeException(
            sprintf('Status HTTP invalido em %s.', $url)
        );
    }

    $decodedBody = trim($body) === ''
        ? []
        : json_decode(
            $body,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

    if (!is_array($decodedBody)) {
        throw new RuntimeException(
            sprintf('Resposta JSON invalida em %s.', $url)
        );
    }

    $headers = [];

    foreach (array_slice($http_response_header, 1) as $headerLine) {
        if (!str_contains($headerLine, ':')) {
            continue;
        }

        [$name, $value] = explode(':', $headerLine, 2);
        $headers[strtolower(trim($name))] = trim($value);
    }

    return [
        (int) $matches[1],
        $decodedBody,
        $headers,
        $http_response_header,
    ];
}

function verifyHttpAuthorization(
    string $baseUrl,
    PDO $connection
): void {
    [$missingStatus, $missingBody] = requestJson(
        $baseUrl . '/auth/me'
    );

    assertSmoke(
        $missingStatus === 401,
        'GET /auth/me sem token deve responder HTTP 401.'
    );
    assertSmoke(
        ($missingBody['error'] ?? null)
            === 'Autenticacao necessaria.',
        'Rota protegida sem token retornou mensagem inesperada.'
    );

    [$invalidStatus] = requestJson(
        $baseUrl . '/auth/me',
        requestHeaders: [
            'Cookie: access_token=token-invalido',
        ]
    );

    assertSmoke(
        $invalidStatus === 401,
        'GET /auth/me com token invalido deve responder HTTP 401.'
    );

    [$ordersWithoutAuthenticationStatus] = requestJson(
        $baseUrl . '/pedidos'
    );

    assertSmoke(
        $ordersWithoutAuthenticationStatus === 401,
        'GET /pedidos sem token deve responder HTTP 401.'
    );

    [$clientsWithoutAuthenticationStatus] = requestJson(
        $baseUrl . '/clientes'
    );

    assertSmoke(
        $clientsWithoutAuthenticationStatus === 401,
        'GET /clientes sem token deve responder HTTP 401.'
    );

    $lookupHasher = new LookupHasher(
        Environment::getRequired('DATA_LOOKUP_KEY')
    );

    $users = new PdoUserRepository(
        $connection,
        new DataCipher(
            Environment::getRequired('DATA_ENCRYPTION_KEY')
        ),
        $lookupHasher
    );

    $blacklist = new PdoTokenBlacklistRepository(
        $connection,
        $lookupHasher
    );

    $jwtService = new JwtService(
        AuthConfig::fromEnvironment()
    );

    $userIds = [];
    $clientIds = [];

    try {
        $suffix = bin2hex(random_bytes(8));
        $password = 'SenhaSmoke123';

        $operator = $users->create(
            'Smoke Operador',
            sprintf('operator-%s@example.test', $suffix),
            'smoke_operator_' . $suffix,
            password_hash($password, PASSWORD_DEFAULT),
            UserProfile::Operator
        );
        $userIds[] = $operator->id;

        $admin = $users->create(
            'Smoke Admin',
            sprintf('admin-%s@example.test', $suffix),
            'smoke_admin_' . $suffix,
            password_hash($password, PASSWORD_DEFAULT),
            UserProfile::Admin
        );
        $userIds[] = $admin->id;

        [
            $operatorLoginStatus,
            $operatorLoginBody,
            ,
            $operatorLoginHeaders,
        ] = requestJson(
            $baseUrl . '/auth/login',
            'POST',
            jsonBody: [
                'usuario' => $operator->username,
                'senha' => $password,
            ]
        );

        assertSmoke(
            $operatorLoginStatus === 200,
            'Login HTTP do OPERADOR deve responder HTTP 200.'
        );
        assertSmoke(
            ($operatorLoginBody['user']['perfil'] ?? null)
                === 'OPERADOR',
            'Login HTTP nao retornou o perfil OPERADOR.'
        );

        $operatorCookies = extractCookies(
            $operatorLoginHeaders
        );

        assertAuthenticationCookies($operatorCookies);

        [$meStatus, $meBody] = requestJson(
            $baseUrl . '/auth/me',
            requestHeaders: [cookieHeader($operatorCookies)]
        );

        assertSmoke(
            $meStatus === 200,
            'OPERADOR deve acessar uma rota autenticada comum.'
        );
        assertSmoke(
            ($meBody['user']['id'] ?? null) === $operator->id,
            'GET /auth/me retornou um usuario inesperado.'
        );

        $clientRepository = new PdoClientRepository(
            $connection,
            new DataCipher(
                Environment::getRequired('DATA_ENCRYPTION_KEY')
            )
        );
        $orderClient = $clientRepository->create(
            'Cliente do Pedido ' . $suffix,
            '(11) 97777-1234'
        );
        $clientIds[] = $orderClient->id;

        [$clientsStatus, $clientsBody] = requestJson(
            $baseUrl . '/clientes',
            requestHeaders: [cookieHeader($operatorCookies)]
        );

        assertSmoke(
            $clientsStatus === 200
                && is_array($clientsBody['clients'] ?? null),
            'OPERADOR deve listar clientes com HTTP 200.'
        );

        [$operatorCreateClientStatus] = requestJson(
            $baseUrl . '/clientes',
            'POST',
            [
                cookieHeader($operatorCookies),
                'X-CSRF-Token: '
                    . $operatorCookies['csrf_token'],
            ],
            [
                'nome' => 'Cliente indevido',
                'telefone' => '11999999999',
            ]
        );

        assertSmoke(
            $operatorCreateClientStatus === 403,
            'OPERADOR nao deve criar clientes.'
        );

        [$ordersStatus, $ordersBody] = requestJson(
            $baseUrl . '/pedidos',
            requestHeaders: [cookieHeader($operatorCookies)]
        );

        assertSmoke(
            $ordersStatus === 200
                && isset($ordersBody['orders'])
                && is_array($ordersBody['orders']),
            'OPERADOR deve listar pedidos com HTTP 200.'
        );

        [$orderWithoutCsrfStatus] = requestJson(
            $baseUrl . '/pedidos',
            'POST',
            [cookieHeader($operatorCookies)],
            [
                'cliente_id' => $orderClient->id,
                'descricao' => 'Pedido sem CSRF',
                'valor_total' => '10.00',
                'status' => 'PENDENTE',
            ]
        );

        assertSmoke(
            $orderWithoutCsrfStatus === 403,
            'POST /pedidos sem CSRF deve responder HTTP 403.'
        );

        [$invalidOrderStatus, $invalidOrderBody] = requestJson(
            $baseUrl . '/pedidos',
            'POST',
            [
                cookieHeader($operatorCookies),
                'X-CSRF-Token: '
                    . $operatorCookies['csrf_token'],
            ],
            [
                'cliente_id' => 0,
                'descricao' => '',
                'valor_total' => '0',
                'status' => 'CANCELADO',
            ]
        );

        assertSmoke(
            $invalidOrderStatus === 422,
            'Pedido invalido deve responder HTTP 422.'
        );
        assertSmoke(
            isset($invalidOrderBody['fields']['cliente_id'])
                && isset($invalidOrderBody['fields']['descricao'])
                && isset($invalidOrderBody['fields']['valor_total'])
                && isset($invalidOrderBody['fields']['status']),
            'Pedido invalido deve identificar todos os campos.'
        );

        [$createOrderStatus, $createOrderBody] = requestJson(
            $baseUrl . '/pedidos',
            'POST',
            [
                cookieHeader($operatorCookies),
                'X-CSRF-Token: '
                    . $operatorCookies['csrf_token'],
            ],
            [
                'cliente_id' => $orderClient->id,
                'descricao' => 'Pedido criado pelo smoke test',
                'valor_total' => '149.90',
                'status' => 'PENDENTE',
            ]
        );

        assertSmoke(
            $createOrderStatus === 201,
            'OPERADOR deve criar pedido com HTTP 201.'
        );

        $orderId = $createOrderBody['order']['id'] ?? null;

        assertSmoke(
            is_int($orderId) && $orderId > 0,
            'POST /pedidos nao retornou um id valido.'
        );
        assertSmoke(
            ($createOrderBody['order']['criado_por'] ?? null)
                === $operator->id,
            'Pedido nao foi vinculado ao usuario autenticado.'
        );
        assertSmoke(
            ($createOrderBody['order']['cliente_id'] ?? null)
                === $orderClient->id,
            'Pedido nao foi vinculado ao cliente selecionado.'
        );
        assertSmoke(
            ($createOrderBody['order']['valor_total'] ?? null)
                === '149.90',
            'Pedido nao retornou o valor total esperado.'
        );

        [$showOrderStatus, $showOrderBody] = requestJson(
            $baseUrl . '/pedidos/' . $orderId,
            requestHeaders: [cookieHeader($operatorCookies)]
        );

        assertSmoke(
            $showOrderStatus === 200
                && ($showOrderBody['order']['id'] ?? null)
                    === $orderId,
            'GET /pedidos/{id} nao retornou o pedido criado.'
        );

        [$updateOrderWithoutCsrfStatus] = requestJson(
            $baseUrl . '/pedidos/' . $orderId,
            'PUT',
            [cookieHeader($operatorCookies)],
            [
                'cliente_id' => $orderClient->id,
                'descricao' => 'Atualizacao sem CSRF',
                'valor_total' => '175.00',
                'status' => 'EM_PROCESSAMENTO',
            ]
        );

        assertSmoke(
            $updateOrderWithoutCsrfStatus === 403,
            'PUT /pedidos/{id} sem CSRF deve responder HTTP 403.'
        );

        [$updateOrderStatus, $updateOrderBody] = requestJson(
            $baseUrl . '/pedidos/' . $orderId,
            'PUT',
            [
                cookieHeader($operatorCookies),
                'X-CSRF-Token: '
                    . $operatorCookies['csrf_token'],
            ],
            [
                'cliente_id' => $orderClient->id,
                'descricao' => 'Pedido atualizado pelo smoke test',
                'valor_total' => '199.50',
                'status' => 'CONCLUIDO',
            ]
        );

        assertSmoke(
            $updateOrderStatus === 200
                && ($updateOrderBody['order']['status'] ?? null)
                    === 'CONCLUIDO'
                && ($updateOrderBody['order']['valor_total'] ?? null)
                    === '199.50',
            'PUT /pedidos/{id} nao atualizou o pedido.'
        );

        [$missingOrderStatus, $missingOrderBody] = requestJson(
            $baseUrl . '/pedidos/999999999',
            requestHeaders: [cookieHeader($operatorCookies)]
        );

        assertSmoke(
            $missingOrderStatus === 404
                && isset($missingOrderBody['error']),
            'Pedido inexistente deve responder HTTP 404.'
        );

        [
            $deleteOrderStatus,
            ,
            $deleteOrderHeaders,
        ] = requestJson(
            $baseUrl . '/pedidos/' . $orderId,
            'DELETE',
            [cookieHeader($operatorCookies)]
        );

        assertSmoke(
            $deleteOrderStatus === 405,
            'DELETE /pedidos/{id} nao deve ser implementado.'
        );
        assertSmoke(
            ($deleteOrderHeaders['allow'] ?? null)
                === 'GET, PUT',
            'DELETE /pedidos/{id} deve informar Allow: GET, PUT.'
        );

        [$operatorUsersStatus, $operatorUsersBody] = requestJson(
            $baseUrl . '/usuarios',
            requestHeaders: [cookieHeader($operatorCookies)]
        );

        assertSmoke(
            $operatorUsersStatus === 403,
            'OPERADOR nao deve acessar GET /usuarios.'
        );
        assertSmoke(
            ($operatorUsersBody['error'] ?? null)
                === 'Acesso nao permitido.',
            'Bloqueio de perfil retornou mensagem inesperada.'
        );

        [$operatorCreateStatus] = requestJson(
            $baseUrl . '/auth/register',
            'POST',
            [
                cookieHeader($operatorCookies),
                'X-CSRF-Token: '
                    . $operatorCookies['csrf_token'],
            ],
            [
                'nome' => 'Usuario indevido',
                'email' => 'indevido@example.test',
                'usuario' => 'usuario_indevido',
                'senha' => 'SenhaSegura@123',
                'perfil' => 'ADMIN',
            ]
        );

        assertSmoke(
            $operatorCreateStatus === 403,
            'OPERADOR nao deve criar usuarios.'
        );

        [
            $adminLoginStatus,
            $adminLoginBody,
            ,
            $adminLoginHeaders,
        ] = requestJson(
            $baseUrl . '/auth/login',
            'POST',
            jsonBody: [
                'usuario' => $admin->username,
                'senha' => $password,
            ]
        );

        assertSmoke(
            $adminLoginStatus === 200,
            'Login HTTP do ADMIN deve responder HTTP 200.'
        );
        assertSmoke(
            ($adminLoginBody['user']['perfil'] ?? null)
                === 'ADMIN',
            'Login HTTP nao retornou o perfil ADMIN.'
        );

        $adminCookies = extractCookies($adminLoginHeaders);

        assertAuthenticationCookies($adminCookies);

        [$clientWithoutCsrfStatus] = requestJson(
            $baseUrl . '/clientes',
            'POST',
            [cookieHeader($adminCookies)],
            [
                'nome' => 'Cliente sem CSRF',
                'telefone' => '11999999999',
            ]
        );

        assertSmoke(
            $clientWithoutCsrfStatus === 403,
            'POST /clientes sem CSRF deve responder HTTP 403.'
        );

        [$invalidClientStatus, $invalidClientBody] = requestJson(
            $baseUrl . '/clientes',
            'POST',
            [
                cookieHeader($adminCookies),
                'X-CSRF-Token: ' . $adminCookies['csrf_token'],
            ],
            [
                'nome' => '',
                'telefone' => 'invalido',
            ]
        );

        assertSmoke(
            $invalidClientStatus === 422
                && isset($invalidClientBody['fields']['nome'])
                && isset($invalidClientBody['fields']['telefone']),
            'Cliente invalido deve identificar nome e telefone.'
        );

        $clientName = 'Cliente Smoke ' . $suffix;
        $clientPhone = '(11) 99999-1234';

        [$createClientStatus, $createClientBody] = requestJson(
            $baseUrl . '/clientes',
            'POST',
            [
                cookieHeader($adminCookies),
                'X-CSRF-Token: ' . $adminCookies['csrf_token'],
            ],
            [
                'nome' => $clientName,
                'telefone' => $clientPhone,
            ]
        );

        assertSmoke(
            $createClientStatus === 201,
            'ADMIN deve criar cliente com HTTP 201.'
        );

        $clientId = $createClientBody['client']['id'] ?? null;

        assertSmoke(
            is_int($clientId) && $clientId > 0,
            'POST /clientes nao retornou um id valido.'
        );
        $clientIds[] = $clientId;

        $encryptedClientStatement = $connection->prepare(
            <<<'SQL'
            SELECT
                nome_criptografado,
                telefone_criptografado
            FROM clientes
            WHERE id = :id
            SQL
        );
        $encryptedClientStatement->execute(['id' => $clientId]);
        $encryptedClient = $encryptedClientStatement->fetch();

        assertSmoke(
            is_array($encryptedClient)
                && $encryptedClient['nome_criptografado']
                    !== $clientName
                && $encryptedClient['telefone_criptografado']
                    !== $clientPhone,
            'Nome e telefone do cliente foram persistidos em texto puro.'
        );

        [$updateClientStatus, $updateClientBody] = requestJson(
            $baseUrl . '/clientes/' . $clientId,
            'PUT',
            [
                cookieHeader($adminCookies),
                'X-CSRF-Token: ' . $adminCookies['csrf_token'],
            ],
            [
                'nome' => 'Cliente Smoke Atualizado',
                'telefone' => '(11) 98888-4321',
            ]
        );

        assertSmoke(
            $updateClientStatus === 200
                && ($updateClientBody['client']['nome'] ?? null)
                    === 'Cliente Smoke Atualizado',
            'PUT /clientes/{id} nao atualizou o cliente.'
        );

        [$deleteClientStatus] = requestJson(
            $baseUrl . '/clientes/' . $clientId,
            'DELETE',
            [
                cookieHeader($adminCookies),
                'X-CSRF-Token: ' . $adminCookies['csrf_token'],
            ]
        );

        assertSmoke(
            $deleteClientStatus === 204,
            'DELETE /clientes/{id} deve responder HTTP 204.'
        );

        [$deleteClientAgainStatus] = requestJson(
            $baseUrl . '/clientes/' . $clientId,
            'DELETE',
            [
                cookieHeader($adminCookies),
                'X-CSRF-Token: ' . $adminCookies['csrf_token'],
            ]
        );

        assertSmoke(
            $deleteClientAgainStatus === 404,
            'Cliente excluido deve responder HTTP 404.'
        );

        [$crossSessionCsrfStatus] = requestJson(
            $baseUrl . '/pedidos',
            'POST',
            [
                cookieHeader([
                    'access_token' =>
                        $operatorCookies['access_token'],
                    'csrf_token' =>
                        $adminCookies['csrf_token'],
                ]),
                'X-CSRF-Token: '
                    . $adminCookies['csrf_token'],
            ],
            [
                'cliente_id' => $orderClient->id,
                'descricao' => 'CSRF de outra sessao',
                'valor_total' => '10.00',
                'status' => 'PENDENTE',
            ]
        );

        assertSmoke(
            $crossSessionCsrfStatus === 403,
            'CSRF de outra sessao deve responder HTTP 403.'
        );

        [
            $oldCreateRouteStatus,
            ,
            $oldCreateRouteHeaders,
        ] = requestJson(
            $baseUrl . '/usuarios',
            'POST',
            [
                cookieHeader($adminCookies),
                'X-CSRF-Token: ' . $adminCookies['csrf_token'],
            ],
            [
                'nome' => 'Rota Antiga',
                'email' => 'rota-antiga@example.test',
                'usuario' => 'rota_antiga',
                'senha' => 'SenhaSegura@123',
                'perfil' => 'OPERADOR',
            ]
        );

        assertSmoke(
            $oldCreateRouteStatus === 405,
            'POST /usuarios nao deve mais criar usuarios.'
        );
        assertSmoke(
            ($oldCreateRouteHeaders['allow'] ?? null)
                === 'GET',
            'POST /usuarios deve informar Allow: GET.'
        );

        [$missingCsrfStatus] = requestJson(
            $baseUrl . '/auth/register',
            'POST',
            [cookieHeader($adminCookies)],
            [
                'nome' => 'Sem CSRF',
                'email' => 'sem-csrf@example.test',
                'usuario' => 'sem_csrf',
                'senha' => 'SenhaSegura@123',
                'perfil' => 'OPERADOR',
            ]
        );

        assertSmoke(
            $missingCsrfStatus === 403,
            'POST /auth/register sem CSRF deve responder HTTP 403.'
        );

        [$weakPasswordStatus, $weakPasswordBody] = requestJson(
            $baseUrl . '/auth/register',
            'POST',
            [
                cookieHeader($adminCookies),
                'X-CSRF-Token: ' . $adminCookies['csrf_token'],
            ],
            [
                'nome' => 'Senha Fraca',
                'email' => 'senha-fraca@example.test',
                'usuario' => 'senha_fraca',
                'senha' => 'senhafraca',
                'perfil' => 'OPERADOR',
            ]
        );

        assertSmoke(
            $weakPasswordStatus === 422,
            'Senha fraca em POST /auth/register deve responder HTTP 422.'
        );
        assertSmoke(
            isset($weakPasswordBody['fields']['senha']),
            'Senha fraca deve retornar erro no campo senha.'
        );

        $createdUsername = 'created_operator_' . $suffix;

        [$createStatus, $createBody] = requestJson(
            $baseUrl . '/auth/register',
            'POST',
            [
                cookieHeader($adminCookies),
                'X-CSRF-Token: ' . $adminCookies['csrf_token'],
            ],
            [
                'nome' => 'Operador Criado',
                'email' => sprintf(
                    'created-%s@example.test',
                    $suffix
                ),
                'usuario' => $createdUsername,
                'senha' => 'SenhaSegura@123',
                'perfil' => 'OPERADOR',
            ]
        );

        assertSmoke(
            $createStatus === 201,
            'ADMIN deve criar usuario com HTTP 201.'
        );
        assertSmoke(
            ($createBody['user']['usuario'] ?? null)
                === $createdUsername
            && ($createBody['user']['perfil'] ?? null)
                === 'OPERADOR',
            'POST /auth/register retornou usuario inesperado.'
        );
        assertSmoke(
            !isset($createBody['user']['senha'])
                && !isset($createBody['user']['senha_hash']),
            'POST /auth/register nao deve retornar a senha.'
        );

        $createdUserId = $createBody['user']['id'] ?? null;

        assertSmoke(
            is_int($createdUserId) && $createdUserId > 0,
            'POST /auth/register nao retornou um id valido.'
        );

        $userIds[] = $createdUserId;

        [$duplicateStatus, $duplicateBody] = requestJson(
            $baseUrl . '/auth/register',
            'POST',
            [
                cookieHeader($adminCookies),
                'X-CSRF-Token: ' . $adminCookies['csrf_token'],
            ],
            [
                'nome' => 'Operador Duplicado',
                'email' => sprintf(
                    'created-%s@example.test',
                    $suffix
                ),
                'usuario' => $createdUsername,
                'senha' => 'SenhaSegura@123',
                'perfil' => 'OPERADOR',
            ]
        );

        assertSmoke(
            $duplicateStatus === 422,
            'Usuario duplicado deve responder HTTP 422.'
        );
        assertSmoke(
            isset($duplicateBody['fields']['email'])
                && isset($duplicateBody['fields']['usuario']),
            'Duplicidade deve identificar email e usuario.'
        );

        [$operatorUpdateStatus] = requestJson(
            $baseUrl . '/usuarios/' . $createdUserId,
            'PUT',
            [
                cookieHeader($operatorCookies),
                'X-CSRF-Token: '
                    . $operatorCookies['csrf_token'],
            ],
            [
                'nome' => 'Alteracao Indevida',
                'email' => 'alteracao-indevida@example.test',
                'usuario' => 'alteracao_indevida',
                'perfil' => 'ADMIN',
                'ativo' => true,
            ]
        );

        assertSmoke(
            $operatorUpdateStatus === 403,
            'OPERADOR nao deve atualizar usuarios.'
        );

        [$operatorDeleteStatus] = requestJson(
            $baseUrl . '/usuarios/' . $createdUserId,
            'DELETE',
            [
                cookieHeader($operatorCookies),
                'X-CSRF-Token: '
                    . $operatorCookies['csrf_token'],
            ]
        );

        assertSmoke(
            $operatorDeleteStatus === 403,
            'OPERADOR nao deve excluir usuarios.'
        );

        [$updateWithoutCsrfStatus] = requestJson(
            $baseUrl . '/usuarios/' . $createdUserId,
            'PUT',
            [cookieHeader($adminCookies)],
            [
                'nome' => 'Sem CSRF',
                'email' => 'update-sem-csrf@example.test',
                'usuario' => 'update_sem_csrf',
                'perfil' => 'OPERADOR',
                'ativo' => true,
            ]
        );

        assertSmoke(
            $updateWithoutCsrfStatus === 403,
            'PUT /usuarios/{id} sem CSRF deve responder HTTP 403.'
        );

        [$selfDeactivateStatus, $selfDeactivateBody] = requestJson(
            $baseUrl . '/usuarios/' . $admin->id,
            'PUT',
            [
                cookieHeader($adminCookies),
                'X-CSRF-Token: ' . $adminCookies['csrf_token'],
            ],
            [
                'nome' => $admin->name,
                'email' => $admin->email,
                'usuario' => $admin->username,
                'perfil' => 'ADMIN',
                'ativo' => false,
            ]
        );

        assertSmoke(
            $selfDeactivateStatus === 422,
            'ADMIN nao deve desativar a propria conta.'
        );
        assertSmoke(
            isset($selfDeactivateBody['fields']['ativo']),
            'Autodesativacao deve retornar erro no campo ativo.'
        );

        $updatedUsername = 'updated_operator_' . $suffix;
        $updatedPassword = 'NovaSenha@123';

        [$updateStatus, $updateBody] = requestJson(
            $baseUrl . '/usuarios/' . $createdUserId,
            'PUT',
            [
                cookieHeader($adminCookies),
                'X-CSRF-Token: ' . $adminCookies['csrf_token'],
            ],
            [
                'nome' => 'Operador Atualizado',
                'email' => sprintf(
                    'updated-%s@example.test',
                    $suffix
                ),
                'usuario' => $updatedUsername,
                'senha' => $updatedPassword,
                'perfil' => 'OPERADOR',
                'ativo' => true,
            ]
        );

        assertSmoke(
            $updateStatus === 200,
            'ADMIN deve atualizar usuario com HTTP 200.'
        );
        assertSmoke(
            ($updateBody['user']['usuario'] ?? null)
                === $updatedUsername
            && ($updateBody['user']['nome'] ?? null)
                === 'Operador Atualizado',
            'PUT /usuarios/{id} retornou dados inesperados.'
        );

        [
            $updatedLoginStatus,
            ,
            ,
            $updatedLoginHeaders,
        ] = requestJson(
            $baseUrl . '/auth/login',
            'POST',
            jsonBody: [
                'usuario' => $updatedUsername,
                'senha' => $updatedPassword,
            ]
        );

        assertSmoke(
            $updatedLoginStatus === 200,
            'Usuario atualizado deve autenticar com a nova senha.'
        );

        $updatedCookies = extractCookies(
            $updatedLoginHeaders
        );

        assertAuthenticationCookies($updatedCookies);

        [$selfDeleteStatus, $selfDeleteBody] = requestJson(
            $baseUrl . '/usuarios/' . $admin->id,
            'DELETE',
            [
                cookieHeader($adminCookies),
                'X-CSRF-Token: ' . $adminCookies['csrf_token'],
            ]
        );

        assertSmoke(
            $selfDeleteStatus === 422,
            'ADMIN nao deve excluir a propria conta.'
        );
        assertSmoke(
            isset($selfDeleteBody['fields']['id']),
            'Autoexclusao deve retornar erro no campo id.'
        );

        [$deleteStatus, $deleteBody] = requestJson(
            $baseUrl . '/usuarios/' . $createdUserId,
            'DELETE',
            [
                cookieHeader($adminCookies),
                'X-CSRF-Token: ' . $adminCookies['csrf_token'],
            ]
        );

        assertSmoke(
            $deleteStatus === 204 && $deleteBody === [],
            'ADMIN deve excluir usuario com HTTP 204.'
        );

        [$deletedSessionStatus] = requestJson(
            $baseUrl . '/auth/me',
            requestHeaders: [cookieHeader($updatedCookies)]
        );

        assertSmoke(
            $deletedSessionStatus === 401,
            'Usuario excluido nao deve manter acesso a API.'
        );

        [$deleteAgainStatus] = requestJson(
            $baseUrl . '/usuarios/' . $createdUserId,
            'DELETE',
            [
                cookieHeader($adminCookies),
                'X-CSRF-Token: ' . $adminCookies['csrf_token'],
            ]
        );

        assertSmoke(
            $deleteAgainStatus === 404,
            'Usuario ja excluido deve responder HTTP 404.'
        );

        [$adminUsersStatus, $adminUsersBody] = requestJson(
            $baseUrl . '/usuarios',
            requestHeaders: [cookieHeader($adminCookies)]
        );

        assertSmoke(
            $adminUsersStatus === 200,
            'ADMIN deve acessar GET /usuarios.'
        );
        assertSmoke(
            is_array($adminUsersBody['users'] ?? null),
            'GET /usuarios deve retornar uma lista.'
        );

        $operatorAccessToken =
            $operatorCookies['access_token'] ?? '';

        $operatorClaims = $jwtService->decodeAccessToken(
            $operatorAccessToken
        );

        $blacklist->add(
            $operatorClaims,
            TokenRevocationReason::AdminRevoked
        );

        [$revokedStatus] = requestJson(
            $baseUrl . '/auth/me',
            requestHeaders: [cookieHeader($operatorCookies)]
        );

        assertSmoke(
            $revokedStatus === 401,
            'Access token revogado deve responder HTTP 401.'
        );
    } finally {
        deleteSmokeUsers($connection, $userIds);
        deleteSmokeClients($connection, $clientIds);
    }
}

/**
 * @param list<string> $responseHeaders
 * @return array<string, string>
 */
function extractCookies(array $responseHeaders): array
{
    $cookies = [];

    foreach ($responseHeaders as $header) {
        if (
            preg_match(
                '/\ASet-Cookie:\s*([^=;]+)=([^;]*)/i',
                $header,
                $matches
            ) !== 1
        ) {
            continue;
        }

        $cookies[$matches[1]] = rawurldecode($matches[2]);
    }

    return $cookies;
}

/**
 * @param array<string, string> $cookies
 */
function assertAuthenticationCookies(array $cookies): void
{
    foreach (
        ['access_token', 'refresh_token', 'csrf_token']
        as $cookie
    ) {
        assertSmoke(
            isset($cookies[$cookie]) && $cookies[$cookie] !== '',
            sprintf('Cookie de autenticacao ausente: %s.', $cookie)
        );
    }
}

/**
 * @param array<string, string> $cookies
 */
function cookieHeader(array $cookies): string
{
    $parts = [];

    foreach ($cookies as $name => $value) {
        $parts[] = sprintf('%s=%s', $name, rawurlencode($value));
    }

    return 'Cookie: ' . implode('; ', $parts);
}

/**
 * @param list<int> $userIds
 */
function deleteSmokeUsers(
    PDO $connection,
    array $userIds
): void {
    if ($userIds === []) {
        return;
    }

    $placeholders = implode(
        ', ',
        array_fill(0, count($userIds), '?')
    );

    foreach (
        ['token_blacklist', 'refresh_tokens']
        as $table
    ) {
        $statement = $connection->prepare(
            sprintf(
                'DELETE FROM %s WHERE usuario_id IN (%s)',
                $table,
                $placeholders
            )
        );
        $statement->execute($userIds);
    }

    $orders = $connection->prepare(
        sprintf(
            'DELETE FROM pedidos WHERE criado_por IN (%s)',
            $placeholders
        )
    );
    $orders->execute($userIds);

    $users = $connection->prepare(
        sprintf(
            'DELETE FROM usuarios WHERE id IN (%s)',
            $placeholders
        )
    );
    $users->execute($userIds);
}

/**
 * @param list<int> $clientIds
 */
function deleteSmokeClients(
    PDO $connection,
    array $clientIds
): void {
    if ($clientIds === []) {
        return;
    }

    $placeholders = implode(
        ', ',
        array_fill(0, count($clientIds), '?')
    );

    $statement = $connection->prepare(
        sprintf(
            'DELETE FROM clientes WHERE id IN (%s)',
            $placeholders
        )
    );
    $statement->execute($clientIds);
}

function verifyLogoutSecurity(PDO $connection): void
{
    $connection->beginTransaction();

    try {
        $lookupHasher = new LookupHasher(
            Environment::getRequired('DATA_LOOKUP_KEY')
        );

        $users = new PdoUserRepository(
            $connection,
            new DataCipher(
                Environment::getRequired(
                    'DATA_ENCRYPTION_KEY'
                )
            ),
            $lookupHasher
        );

        $refreshTokens = new PdoRefreshTokenRepository(
            $connection,
            $lookupHasher
        );

        $blacklist = new PdoTokenBlacklistRepository(
            $connection,
            $lookupHasher
        );

        $jwtService = new JwtService(
            AuthConfig::fromEnvironment()
        );

        $authentication = new AuthenticationService(
            new PdoAuthenticationRepository(
                $connection,
                $users
            ),
            $jwtService,
            new CsrfTokenService(),
            $refreshTokens,
            $users,
            $blacklist
        );

        $suffix = bin2hex(random_bytes(8));

        $user = $users->create(
            'Smoke Logout',
            sprintf('smoke-%s@example.test', $suffix),
            'smoke_logout_' . $suffix,
            password_hash(
                'SenhaSmoke123',
                PASSWORD_DEFAULT
            ),
            UserProfile::Operator
        );

        $csrfService = new CsrfTokenService();
        $csrfToken = $csrfService->generate();

        $accessToken = $jwtService->issueAccessToken(
            $user->id,
            $csrfService->hash($csrfToken)
        );

        $refreshToken = $jwtService->issueRefreshToken(
            $user->id
        );

        $refreshTokens->register(
            $user->id,
            $refreshToken
        );

        $authentication->logout(
            $accessToken->value,
            $refreshToken->value
        );

        assertSmoke(
            $blacklist->contains($accessToken->jti),
            'Logout nao adicionou o access token a blacklist.'
        );

        $refreshStatement = $connection->prepare(
            <<<'SQL'
            SELECT jti_hash, revoked_at
            FROM refresh_tokens
            WHERE usuario_id = :usuario_id
            SQL
        );

        $refreshStatement->execute([
            'usuario_id' => $user->id,
        ]);

        $storedRefresh = $refreshStatement->fetch();

        assertSmoke(
            is_array($storedRefresh)
                && $storedRefresh['revoked_at'] !== null,
            'Logout nao revogou a familia do refresh token.'
        );

        assertSmoke(
            is_array($storedRefresh)
                && hash_equals(
                    $storedRefresh['jti_hash'],
                    $lookupHasher->hash(
                        $refreshToken->jti,
                        'refresh_tokens.jti'
                    )
                ),
            'Refresh token nao foi persistido de forma protegida.'
        );

        $blacklistStatement = $connection->prepare(
            <<<'SQL'
            SELECT motivo
            FROM token_blacklist
            WHERE usuario_id = :usuario_id
            SQL
        );

        $blacklistStatement->execute([
            'usuario_id' => $user->id,
        ]);

        assertSmoke(
            $blacklistStatement->fetchColumn() === 'LOGOUT',
            'Blacklist nao registrou o motivo do logout.'
        );
    } finally {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    }
}

function assertSmoke(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function writeSuccess(string $message): void
{
    fwrite(
        STDOUT,
        sprintf('[OK] %s%s', $message, PHP_EOL)
    );
}
