<?php

declare(strict_types=1);

namespace App\Http;

use App\Config\AuthConfig;
use App\Dto\AuthenticationResult;

final class AuthenticationCookieService
{
    public const ACCESS_COOKIE = 'access_token';
    public const REFRESH_COOKIE = 'refresh_token';
    public const CSRF_COOKIE = 'csrf_token';

    public function __construct(
        private readonly AuthConfig $config
    ) {
    }

    public function addAuthenticationCookies(
        Response $response,
        AuthenticationResult $authentication
    ): Response {
        $response = $this->addCookie(
            $response,
            self::ACCESS_COOKIE,
            $authentication->accessToken->value,
            '/',
            true,
            $authentication->accessToken->expiresAt,
            $authentication->accessToken->expiresAt
                - $authentication->accessToken->issuedAt
        );

        $response = $this->addCookie(
            $response,
            self::REFRESH_COOKIE,
            $authentication->refreshToken->value,
            '/auth',
            true,
            $authentication->refreshToken->expiresAt,
            $authentication->refreshToken->expiresAt
                - $authentication->refreshToken->issuedAt
        );

        if ($this->config->csrfEnabled) {
            $response = $this->addCookie(
                $response,
                self::CSRF_COOKIE,
                $authentication->csrfToken,
                '/',
                false,
                $authentication->refreshToken->expiresAt,
                $authentication->refreshToken->expiresAt
                    - $authentication->refreshToken->issuedAt
            );
        }

        return $response;
    }

    public function clearAuthenticationCookies(
        Response $response
    ): Response {
        $cookies = [
            [self::ACCESS_COOKIE, '/', true],
            [self::REFRESH_COOKIE, '/auth', true],
            [self::CSRF_COOKIE, '/', false],
        ];

        foreach ($cookies as [$name, $path, $httpOnly]) {
            $response = $this->addCookie(
                $response,
                $name,
                '',
                $path,
                $httpOnly,
                1,
                0
            );
        }

        return $response;
    }

    private function addCookie(
        Response $response,
        string $name,
        string $value,
        string $path,
        bool $httpOnly,
        int $expiresAt,
        int $maxAge
    ): Response {
        $parts = [
            sprintf('%s=%s', $name, rawurlencode($value)),
            sprintf('Path=%s', $path),
            sprintf('Expires=%s', gmdate(DATE_RFC7231, $expiresAt)),
            sprintf('Max-Age=%d', max(0, $maxAge)),
            sprintf('SameSite=%s', $this->config->cookieSameSite),
        ];

        if ($this->config->cookieSecure) {
            $parts[] = 'Secure';
        }

        if ($httpOnly) {
            $parts[] = 'HttpOnly';
        }

        return $response->withAddedHeader(
            'Set-Cookie',
            implode('; ', $parts)
        );
    }
}
