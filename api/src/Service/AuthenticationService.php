<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\AuthenticationResult;
use App\Exception\InvalidCredentialsException;
use App\Repository\AuthenticationRepository;
use App\Repository\RefreshTokenRepository;
use App\Security\CsrfTokenService;

final class AuthenticationService
{
    public function __construct(
        private readonly AuthenticationRepository $authentication,
        private readonly JwtService $jwtService,
        private readonly CsrfTokenService $csrfService,
        private readonly RefreshTokenRepository $refreshTokens
    ) {
    }

    public function login(
        string $username,
        string $password
    ): AuthenticationResult {
        $username = trim($username);

        if ($username === '' || $password === '') {
            throw new InvalidCredentialsException(
                'Usuario ou senha invalidos.'
            );
        }

        $user = $this->authentication->authenticate(
            $username,
            $password
        );

        if ($user === null) {
            throw new InvalidCredentialsException(
                'Usuario ou senha invalidos.'
            );
        }

        $csrfToken = $this->csrfService->generate();
        $csrfHash = $this->csrfService->hash($csrfToken);

        $accessToken = $this->jwtService->issueAccessToken(
            $user->id,
            $csrfHash
        );

        $refreshToken = $this->jwtService->issueRefreshToken(
            $user->id
        );

        $this->refreshTokens->register(
            $user->id,
            $refreshToken
        );

        return new AuthenticationResult(
            user: $user,
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            csrfToken: $csrfToken
        );
    }
}
