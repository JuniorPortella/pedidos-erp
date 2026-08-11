<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Response;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testCreatesJsonResponse(): void
    {
        $response = Response::json(
            [
                'message' => 'Pedido criado.',
                'id' => 10,
            ],
            201
        );

        self::assertSame(201, $response->status());

        self::assertSame(
            [
                'message' => 'Pedido criado.',
                'id' => 10,
            ],
            json_decode(
                $response->body(),
                true,
                512,
                JSON_THROW_ON_ERROR
            )
        );

        self::assertSame(
            ['application/json; charset=utf-8'],
            $response->headerValues('Content-Type')
        );
    }

    public function testCreatesEmptyResponse(): void
    {
        $response = Response::empty();

        self::assertSame(204, $response->status());
        self::assertSame('', $response->body());
        self::assertSame([], $response->headers());
    }

    public function testReplacesHeaderWithoutChangingOriginal(): void
    {
        $original = Response::json(['status' => 'ok']);

        $first = $original->withHeader(
            'X-Request-Id',
            'primeiro'
        );

        $second = $first->withHeader(
            'x-request-id',
            'segundo'
        );

        self::assertSame(
            [],
            $original->headerValues('X-Request-Id')
        );

        self::assertSame(
            ['primeiro'],
            $first->headerValues('X-Request-Id')
        );

        self::assertSame(
            ['segundo'],
            $second->headerValues('X-Request-Id')
        );
    }

    public function testAddsRepeatedHeaders(): void
    {
        $response = Response::json(['status' => 'ok'])
            ->withAddedHeader(
                'Set-Cookie',
                'access_token=token1; HttpOnly'
            )
            ->withAddedHeader(
                'Set-Cookie',
                'refresh_token=token2; HttpOnly'
            );

        self::assertSame(
            [
                'access_token=token1; HttpOnly',
                'refresh_token=token2; HttpOnly',
            ],
            $response->headerValues('Set-Cookie')
        );
    }

    #[DataProvider('invalidStatuses')]
    public function testRejectsInvalidStatus(int $status): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );
        $this->expectExceptionMessage(
            'Status HTTP invalido.'
        );

        Response::empty($status);
    }

    public static function invalidStatuses(): array
    {
        return [
            'below HTTP range' => [99],
            'above HTTP range' => [600],
        ];
    }

    #[DataProvider('invalidHeaders')]
    public function testRejectsInvalidHeader(
        string $name,
        string $value
    ): void {
        $response = Response::json(['status' => 'ok']);

        $this->expectException(
            InvalidArgumentException::class
        );

        $response->withHeader($name, $value);
    }

    public static function invalidHeaders(): array
    {
        return [
            'invalid name' => [
                'X-Test: Injected',
                'value',
            ],
            'line break in value' => [
                'X-Test',
                "value\r\nX-Injected: true",
            ],
        ];
    }

    public function testEmitsJsonBody(): void
    {
        $response = Response::json(
            ['status' => 'ok']
        );

        ob_start();

        try {
            $response->emit();

            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertSame(
            '{"status":"ok"}',
            $output
        );
    }

    public function testDoesNotEmitBodyForNoContent(): void
    {
        $response = Response::json(
            ['ignored' => true],
            204
        );

        ob_start();

        try {
            $response->emit();

            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertSame('', $output);
    }
}
