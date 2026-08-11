<?php

declare(strict_types=1);

use App\Config\Environment;
use App\Database\ConnectionFactory;
use App\Database\MigrationRunner;

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
