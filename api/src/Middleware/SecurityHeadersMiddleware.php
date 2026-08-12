<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Response;

final readonly class SecurityHeadersMiddleware
{
    public function __construct(
        private bool $production
    ) {
    }

    public function add(Response $response): Response
    {
        $response = $response
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader(
                'Content-Security-Policy',
                "default-src 'none'; frame-ancestors 'none'; base-uri 'none'"
            )
            ->withHeader(
                'Permissions-Policy',
                'camera=(), microphone=(), geolocation=()'
            );

        if (!$this->production) {
            return $response;
        }

        return $response->withHeader(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains'
        );
    }
}
