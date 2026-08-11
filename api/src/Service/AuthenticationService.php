<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\AuthenticationResult;
use App\Exception\InvalidCredentialsException;
use App\Exception\InvalidTokenException;
use App\Repository\AuthenticationRepository;
use App\Repository\RefreshTokenRepository;
use App\Repository\UserRepository;
use App\Security\CsrfTokenService;

final class AuthenticationService
{
    public function __construct(
        private readonly AuthenticationRepository $authentication,
        private readonly JwtService $jwtService,
        private readonly CsrfTokenService $csrfService,
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly UserRepository $users
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

    public function refresh(
        string $refreshTokenValue
    ): AuthenticationResult {
        $currentToken = $this->jwtService
            ->decodeRefreshToken($refreshTokenValue);

        $user = $this->users->findById(
            $currentToken->userId
        );

        if (
            $user === null
            || !$user->active
            || $user->isDeleted()
        ) {
            $this->refreshTokens->revokeAllForUser(
                $currentToken->userId
            );

            throw new InvalidTokenException(
                'Token invalido ou expirado.'
            );
        }

        $csrfToken = $this->csrfService->generate();
        $csrfHash = $this->csrfService->hash($csrfToken);

        $accessToken = $this->jwtService->issueAccessToken(
            $user->id,
            $csrfHash
        );

        $replacementRefreshToken = $this->jwtService
            ->issueRefreshToken(
                $user->id,
                $currentToken->familyId
            );

        $this->refreshTokens->rotate(
            $currentToken,
            $replacementRefreshToken
        );

        return new AuthenticationResult(
            user: $user,
            accessToken: $accessToken,
            refreshToken: $replacementRefreshToken,
            csrfToken: $csrfToken
        );
    }
}
