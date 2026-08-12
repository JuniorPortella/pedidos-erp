<?php

declare(strict_types=1);

use App\Config\AuthConfig;
use App\Config\Environment;
use App\Database\ConnectionFactory;
use App\Database\MigrationRunner;
use App\Entity\TokenRevocationReason;
use App\Entity\UserProfile;
use App\Repository\PdoAuthenticationRepository;
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
    writeSuccess('API respondeu ao healthcheck');

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
        'pedidos',
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
    ?array $jsonBody = null
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

    $decodedBody = json_decode(
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
