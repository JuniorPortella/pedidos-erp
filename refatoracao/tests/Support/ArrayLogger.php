<?php

declare(strict_types=1);

namespace Tests\Support;

use Refatoracao\Logging\Logger;

final class ArrayLogger implements Logger
{
    /** @var list<array{message: string, context: array<string, mixed>}> */
    public array $infoRecords = [];

    /** @var list<array{message: string, context: array<string, mixed>}> */
    public array $errorRecords = [];

    public function info(string $message, array $context = []): void
    {
        $this->infoRecords[] = [
            'message' => $message,
            'context' => $context,
        ];
    }

    public function error(string $message, array $context = []): void
    {
        $this->errorRecords[] = [
            'message' => $message,
            'context' => $context,
        ];
    }
}
