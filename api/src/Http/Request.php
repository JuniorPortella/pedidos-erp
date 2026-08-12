<?php

declare(strict_types=1);

namespace App\Http;

use App\Exception\InvalidJsonBodyException;
use App\Exception\PayloadTooLargeException;
use App\Exception\UnsupportedMediaTypeException;
use JsonException;
use stdClass;

final readonly class Request
{
    public const MAX_JSON_BODY_BYTES = 1_048_576;

    public string $method;
    public string $path;

    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     * @param array<string, string> $cookies
     */
    public function __construct(
        string $method,
        string $path,
        public array $query = [],
        public array $headers = [],
        public array $cookies = [],
        private string $body = '',
        public string $clientIp = 'unknown'
    ) {
        $method = strtoupper(trim($method));

        if ($method === '') {
            $method = 'GET';
        }

        if ($path === '' || $path[0] !== '/') {
            $path = '/';
        }

        $this->method = $method;
        $this->path = $path;
    }

    public static function fromGlobals(): self
    {
        $body = file_get_contents('php://input');

        return self::fromServer(
            $_SERVER,
            $_GET,
            $_COOKIE,
            $body === false ? '' : $body
        );
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $query
     * @param array<string, string> $cookies
     */
    public static function fromServer(
        array $server,
        array $query = [],
        array $cookies = [],
        string $body = ''
    ): self {
        $method = is_string(
            $server['REQUEST_METHOD'] ?? null
        )
            ? $server['REQUEST_METHOD']
            : 'GET';

        $requestUri = is_string(
            $server['REQUEST_URI'] ?? null
        )
            ? $server['REQUEST_URI']
            : '/';

        $path = parse_url(
            $requestUri,
            PHP_URL_PATH
        );

        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        return new self(
            method: $method,
            path: $path,
            query: $query,
            headers: self::extractHeaders($server),
            cookies: $cookies,
            body: $body,
            clientIp: self::extractClientIp($server)
        );
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if (trim($this->body) === '') {
            return [];
        }

        if (strlen($this->body) > self::MAX_JSON_BODY_BYTES) {
            throw new PayloadTooLargeException(
                'O corpo da requisicao excede o limite de 1 MB.'
            );
        }

        $contentType = strtolower(
            trim(
                explode(
                    ';',
                    $this->header('Content-Type') ?? '',
                    2
                )[0]
            )
        );

        if (
            $contentType !== 'application/json'
            && !(
                str_starts_with($contentType, 'application/')
                && str_ends_with($contentType, '+json')
            )
        ) {
            throw new UnsupportedMediaTypeException(
                'O Content-Type deve ser application/json.'
            );
        }

        try {
            $object = json_decode(
                $this->body,
                false,
                512,
                JSON_THROW_ON_ERROR
            );

            $data = json_decode(
                $this->body,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new InvalidJsonBodyException(
                'O corpo da requisicao deve conter um JSON valido.',
                0,
                $exception
            );
        }

        if (!$object instanceof stdClass || !is_array($data)) {
            throw new InvalidJsonBodyException(
                'O corpo JSON deve ser um objeto.'
            );
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, string>
     */
    private static function extractHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $name => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (str_starts_with($name, 'HTTP_')) {
                $headerName = substr($name, 5);
            } elseif (
                $name === 'CONTENT_TYPE'
                || $name === 'CONTENT_LENGTH'
            ) {
                $headerName = $name;
            } else {
                continue;
            }

            $headerName = strtolower(
                str_replace('_', '-', $headerName)
            );

            $headers[$headerName] = $value;
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $server
     */
    private static function extractClientIp(array $server): string
    {
        $clientIp = $server['REMOTE_ADDR'] ?? null;

        if (
            !is_string($clientIp)
            || filter_var($clientIp, FILTER_VALIDATE_IP) === false
        ) {
            return 'unknown';
        }

        return $clientIp;
    }
}
