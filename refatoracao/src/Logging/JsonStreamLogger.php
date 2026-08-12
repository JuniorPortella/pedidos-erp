<?php

declare(strict_types=1);

namespace Refatoracao\Logging;

use JsonException;
use RuntimeException;

final class JsonStreamLogger implements Logger
{
    /** @var resource */
    private $stream;

    public function __construct(string $destination = 'php://stderr')
    {
        $stream = fopen($destination, 'ab');

        if ($stream === false) {
            throw new RuntimeException(
                'Nao foi possivel abrir o destino dos logs.'
            );
        }

        $this->stream = $stream;
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @throws JsonException
     */
    private function write(
        string $level,
        string $message,
        array $context
    ): void {
        $record = json_encode(
            [
                'timestamp' => gmdate(DATE_ATOM),
                'level' => $level,
                'message' => $message,
                'context' => $context,
            ],
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
        );

        if (fwrite($this->stream, $record . PHP_EOL) === false) {
            throw new RuntimeException(
                'Nao foi possivel gravar o log.'
            );
        }
    }
}
