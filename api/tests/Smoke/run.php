<?php

declare(strict_types=1);

use App\Config\AuthConfig;
use App\Config\Environment;
use App\Database\ConnectionFactory;
use App\Database\MigrationRunner;
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
 *     2: array<string, string>
 * }
 */
function requestJson(
    string $url,
    string $method = 'GET'
): array
{
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'ignore_errors' => true,
            'timeout' => 5,
        ],
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

    return [(int) $matches[1], $decodedBody, $headers];
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
