<?php

declare(strict_types=1);

namespace App\Logging;

use App\Config\Environment;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Processor\UidProcessor;
use Psr\Log\LoggerInterface;

final class LoggerFactory
{
    private function __construct()
    {
    }

    public static function create(
        string $stream = 'php://stderr'
    ): LoggerInterface {
        $minimumLevel = Environment::getBoolean(
            'APP_DEBUG'
        )
            ? Level::Debug
            : Level::Info;

        $formatter = new JsonFormatter(
            batchMode: JsonFormatter::BATCH_MODE_JSON,
            appendNewline: true,
            ignoreEmptyContextAndExtra: false,
            includeStacktraces: true
        );

        $handler = new StreamHandler(
            $stream,
            $minimumLevel
        );

        $handler->setFormatter($formatter);

        $logger = new Logger('pedidos-api');

        $logger->pushProcessor(
            new PsrLogMessageProcessor()
        );

        $logger->pushProcessor(
            new UidProcessor(16)
        );

        $logger->pushHandler($handler);

        return $logger;
    }
}
