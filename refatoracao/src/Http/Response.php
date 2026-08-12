<?php

declare(strict_types=1);

namespace Refatoracao\Http;

use JsonException;

final readonly class Response
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function __construct(
        public int $status,
        public array $data,
        public array $headers = []
    ) {
    }

    /** @throws JsonException */
    public function emit(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');

        foreach ($this->headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value));
        }

        echo json_encode(
            $this->data,
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
        );
    }
}
