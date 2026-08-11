<?php

declare(strict_types=1);

namespace App\Http;

use InvalidArgumentException;

final class Response
{
    /**
     * @var list<array{name: string, value: string}>
     */
    private array $headers = [];

    private function __construct(
        private readonly int $status,
        private readonly string $body
    ) {
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException(
                'Status HTTP invalido.'
            );
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function json(
        array $data,
        int $status = 200
    ): self {
        $body = json_encode(
            $data,
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
        );

        return (new self($status, $body))
            ->withHeader(
                'Content-Type',
                'application/json; charset=utf-8'
            );
    }

    public static function empty(int $status = 204): self
    {
        return new self($status, '');
    }

    public function withHeader(
        string $name,
        string $value
    ): self {
        self::validateHeader($name, $value);

        $normalizedName = strtolower($name);
        $response = clone $this;

        $response->headers = array_values(
            array_filter(
                $response->headers,
                static fn (array $header): bool =>
                    strtolower($header['name'])
                        !== $normalizedName
            )
        );

        $response->headers[] = [
            'name' => $name,
            'value' => $value,
        ];

        return $response;
    }

    public function withAddedHeader(
        string $name,
        string $value
    ): self {
        self::validateHeader($name, $value);

        $response = clone $this;

        $response->headers[] = [
            'name' => $name,
            'value' => $value,
        ];

        return $response;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * @return list<array{name: string, value: string}>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * @return list<string>
     */
    public function headerValues(string $name): array
    {
        $normalizedName = strtolower($name);
        $values = [];

        foreach ($this->headers as $header) {
            if (
                strtolower($header['name'])
                === $normalizedName
            ) {
                $values[] = $header['value'];
            }
        }

        return $values;
    }

    public function emit(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $header) {
            header(
                sprintf(
                    '%s: %s',
                    $header['name'],
                    $header['value']
                ),
                false
            );
        }

        $responseMustNotHaveBody =
            ($this->status >= 100 && $this->status < 200)
            || $this->status === 204
            || $this->status === 304;

        if (!$responseMustNotHaveBody) {
            echo $this->body;
        }
    }

    private static function validateHeader(
        string $name,
        string $value
    ): void {
        if (
            preg_match(
                '/\A[A-Za-z0-9-]+\z/',
                $name
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Nome de header HTTP invalido.'
            );
        }

        if (
            str_contains($value, "\r")
            || str_contains($value, "\n")
        ) {
            throw new InvalidArgumentException(
                'Valor de header HTTP invalido.'
            );
        }
    }
}
