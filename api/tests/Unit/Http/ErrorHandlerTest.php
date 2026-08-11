<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Exception\InvalidCredentialsException;
use App\Exception\InvalidJsonBodyException;
use App\Exception\InvalidTokenException;
use App\Exception\MethodNotAllowedException;
use App\Exception\RefreshTokenNotActiveException;
use App\Exception\RefreshTokenReuseException;
use App\Exception\RouteNotFoundException;
use App\Exception\ValidationException;
use App\Http\ErrorHandler;
use App\Http\Request;
use App\Http\Response;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class ErrorHandlerTest extends TestCase
{
    private TestHandler $logHandler;
    private ErrorHandler $errorHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logHandler = new TestHandler();

        $logger = new Logger('test');
        $logger->pushHandler($this->logHandler);

        $this->errorHandler = new ErrorHandler($logger);
    }

    #[DataProvider('knownExceptions')]
    public function testMapsKnownException(
        Throwable $exception,
        int $expectedStatus,
        string $expectedMessage
    ): void {
        $response = $this->errorHandler->handle(
            $exception
        );

        self::assertSame(
            $expectedStatus,
            $response->status()
        );

        self::assertSame(
            ['error' => $expectedMessage],
            $this->decode($response)
        );

        self::assertSame(
            [],
            $this->logHandler->getRecords()
        );
    }

    public static function knownExceptions(): array
    {
        return [
            'invalid JSON' => [
                new InvalidJsonBodyException(
                    'O corpo da requisicao deve conter um JSON valido.'
                ),
                400,
                'O corpo da requisicao deve conter um JSON valido.',
            ],
            'invalid credentials' => [
                new InvalidCredentialsException(
                    'Detalhe interno.'
                ),
                401,
                'Usuario ou senha invalidos.',
            ],
            'invalid token' => [
                new InvalidTokenException(
                    'Detalhe interno.'
                ),
                401,
                'Token invalido ou expirado.',
            ],
            'inactive refresh token' => [
                new RefreshTokenNotActiveException(
                    'Detalhe interno.'
                ),
                401,
                'Token invalido ou expirado.',
            ],
            'route not found' => [
                new RouteNotFoundException(
                    'Rota nao encontrada.'
                ),
                404,
                'Rota nao encontrada.',
            ],
        ];
    }

    public function testMapsValidationErrors(): void
    {
        $response = $this->errorHandler->handle(
            new ValidationException([
                'email' => 'E-mail invalido.',
                'senha' => 'Senha obrigatoria.',
            ])
        );

        self::assertSame(422, $response->status());

        self::assertSame(
            [
                'error' => 'Dados invalidos.',
                'fields' => [
                    'email' => 'E-mail invalido.',
                    'senha' => 'Senha obrigatoria.',
                ],
            ],
            $this->decode($response)
        );
    }

    public function testMapsMethodNotAllowed(): void
    {
        $response = $this->errorHandler->handle(
            new MethodNotAllowedException([
                'GET',
                'POST',
            ])
        );

        self::assertSame(405, $response->status());

        self::assertSame(
            ['GET, POST'],
            $response->headerValues('Allow')
        );

        self::assertSame(
            [
                'error' => 'Metodo HTTP nao permitido.',
            ],
            $this->decode($response)
        );
    }

    public function testLogsRefreshTokenReuse(): void
    {
        $request = new Request(
            'POST',
            '/auth/refresh'
        );

        $response = $this->errorHandler->handle(
            new RefreshTokenReuseException(
                'Detalhe interno.'
            ),
            $request
        );

        self::assertSame(401, $response->status());

        self::assertSame(
            [
                'error' =>
                    'Sessao invalida. Entre novamente.',
            ],
            $this->decode($response)
        );

        self::assertTrue(
            $this->logHandler->hasWarningThatContains(
                'Reutilizacao de refresh token detectada.'
            )
        );

        $record = $this->logHandler->getRecords()[0];

        self::assertSame(
            'POST',
            $record->context['method']
        );

        self::assertSame(
            '/auth/refresh',
            $record->context['path']
        );
    }

    public function testLogsUnexpectedException(): void
    {
        $exception = new RuntimeException(
            'Falha interna com detalhes sensiveis.'
        );

        $request = new Request(
            'GET',
            '/pedidos'
        );

        $response = $this->errorHandler->handle(
            $exception,
            $request
        );

        self::assertSame(500, $response->status());

        self::assertSame(
            [
                'error' => 'Erro interno do servidor.',
            ],
            $this->decode($response)
        );

        self::assertTrue(
            $this->logHandler->hasErrorThatContains(
                'Erro nao tratado durante a requisicao.'
            )
        );

        $record = $this->logHandler->getRecords()[0];

        self::assertSame(
            $exception,
            $record->context['exception']
        );

        self::assertSame(
            'GET',
            $record->context['method']
        );

        self::assertSame(
            '/pedidos',
            $record->context['path']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        return json_decode(
            $response->body(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }
}
