<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Http\Response;
use App\Middleware\SecurityHeadersMiddleware;
use PHPUnit\Framework\TestCase;

final class SecurityHeadersMiddlewareTest extends TestCase
{
    public function testAddsApiSecurityHeaders(): void
    {
        $response = (new SecurityHeadersMiddleware(false))
            ->add(Response::json(['status' => 'ok']));

        self::assertSame(
            ['no-store'],
            $response->headerValues('Cache-Control')
        );
        self::assertSame(
            ['nosniff'],
            $response->headerValues('X-Content-Type-Options')
        );
        self::assertSame(
            ['DENY'],
            $response->headerValues('X-Frame-Options')
        );
        self::assertSame(
            ['no-referrer'],
            $response->headerValues('Referrer-Policy')
        );
        self::assertSame(
            ["default-src 'none'; frame-ancestors 'none'; base-uri 'none'"],
            $response->headerValues('Content-Security-Policy')
        );
        self::assertSame(
            [],
            $response->headerValues('Strict-Transport-Security')
        );
    }

    public function testAddsHstsOnlyInProduction(): void
    {
        $response = (new SecurityHeadersMiddleware(true))
            ->add(Response::empty());

        self::assertSame(
            ['max-age=31536000; includeSubDomains'],
            $response->headerValues(
                'Strict-Transport-Security'
            )
        );
    }
}
