<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use App\Logging\LoggerFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LoggerFactoryTest extends TestCase
{
    private string $logFile;
    private string|false $originalDebug;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDebug = getenv('APP_DEBUG');

        $logFile = tempnam(
            sys_get_temp_dir(),
            'pedidos-log-'
        );

        if ($logFile === false) {
            throw new RuntimeException(
                'Nao foi possivel criar arquivo temporario.'
            );
        }

        $this->logFile = $logFile;
    }

    protected function tearDown(): void
    {
        if ($this->originalDebug === false) {
            putenv('APP_DEBUG');
        } else {
            putenv(
                'APP_DEBUG=' . $this->originalDebug
            );
        }

        if (is_file($this->logFile)) {
            unlink($this->logFile);
        }

        parent::tearDown();
    }

    public function testWritesStructuredJsonLog(): void
    {
        putenv('APP_DEBUG=true');

        $logger = LoggerFactory::create(
            $this->logFile
        );

        $logger->info(
            'Requisicao concluida com status {status}.',
            [
                'status' => 200,
                'method' => 'GET',
                'path' => '/health',
            ]
        );

        $record = $this->firstRecord();

        self::assertSame(
            'Requisicao concluida com status 200.',
            $record['message']
        );

        self::assertSame(
            'INFO',
            $record['level_name']
        );

        self::assertSame(
            'pedidos-api',
            $record['channel']
        );

        self::assertSame(
            200,
            $record['context']['status']
        );

        self::assertSame(
            'GET',
            $record['context']['method']
        );

        self::assertSame(
            '/health',
            $record['context']['path']
        );

        self::assertMatchesRegularExpression(
            '/\A[a-f0-9]{16}\z/',
            $record['extra']['uid']
        );
    }

    public function testIgnoresDebugWhenDebugIsDisabled(): void
    {
        putenv('APP_DEBUG=false');

        $logger = LoggerFactory::create(
            $this->logFile
        );

        $logger->debug('Log que deve ser ignorado.');
        $logger->info('Log que deve ser gravado.');

        $lines = file(
            $this->logFile,
            FILE_IGNORE_NEW_LINES
                | FILE_SKIP_EMPTY_LINES
        );

        self::assertIsArray($lines);
        self::assertCount(1, $lines);

        $record = json_decode(
            $lines[0],
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame(
            'Log que deve ser gravado.',
            $record['message']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function firstRecord(): array
    {
        $content = file_get_contents(
            $this->logFile
        );

        self::assertIsString($content);
        self::assertNotSame('', trim($content));

        return json_decode(
            trim($content),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }
}
