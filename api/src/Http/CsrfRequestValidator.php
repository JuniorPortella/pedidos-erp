<?php

declare(strict_types=1);

namespace App\Http;

use App\Config\AuthConfig;
use App\Exception\InvalidCsrfTokenException;
use App\Security\CsrfTokenService;
use InvalidArgumentException;

final readonly class CsrfRequestValidator
{
    public const HEADER = 'X-CSRF-Token';

    public function __construct(
        private AuthConfig $config,
        private CsrfTokenService $csrfTokens
    ) {
    }

    public function validate(
        Request $request,
        ?string $tokenCsrfHash = null
    ): void
    {
        if (!$this->config->csrfEnabled) {
            return;
        }

        $cookie = $request->cookie(
            AuthenticationCookieService::CSRF_COOKIE
        );

        $header = $request->header(self::HEADER);

        if ($cookie === null || $header === null) {
            throw new InvalidCsrfTokenException(
                'Token CSRF invalido.'
            );
        }

        try {
            $expectedHash = $this->csrfTokens->hash($cookie);
        } catch (InvalidArgumentException) {
            throw new InvalidCsrfTokenException(
                'Token CSRF invalido.'
            );
        }

        if (!$this->csrfTokens->verify($header, $expectedHash)) {
            throw new InvalidCsrfTokenException(
                'Token CSRF invalido.'
            );
        }

        if (
            $tokenCsrfHash !== null
            && !hash_equals($tokenCsrfHash, $expectedHash)
        ) {
            throw new InvalidCsrfTokenException(
                'Token CSRF invalido.'
            );
        }
    }
}
