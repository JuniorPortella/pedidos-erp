<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\AuthenticationResult;
use App\Dto\TokenClaims;
use App\Entity\TokenRevocationReason;
use App\Exception\InvalidCredentialsException;
use App\Exception\InvalidTokenException;
use App\Repository\AuthenticationRepository;
use App\Repository\RefreshTokenRepository;
use App\Repository\TokenBlacklistRepository;
use App\Repository\UserRepository;
use App\Security\CsrfTokenService;
use App\Security\LoginRateLimiter;

final class AuthenticationService
{
    public function __construct(
        private readonly AuthenticationRepository $authentication,
        private readonly JwtService $jwtService,
        private readonly CsrfTokenService $csrfService,
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly UserRepository $users,
        private readonly TokenBlacklistRepository $blacklist,
        private readonly ?LoginRateLimiter $loginRateLimiter = null
    ) {
    }

    public function login(
        string $username,
        string $password,
        string $clientIp = 'unknown'
    ): AuthenticationResult {
        $username = trim($username);

        if ($username === '' || $password === '') {
            throw new InvalidCredentialsException(
                'Usuario ou senha invalidos.'
            );
        }

        $this->loginRateLimiter?->assertAllowed(
            $username,
            $clientIp
        );

        $user = $this->authentication->authenticate(
            $username,
            $password
        );

        if ($user === null) {
            $this->loginRateLimiter?->registerFailure(
                $username,
                $clientIp
            );

            throw new InvalidCredentialsException(
                'Usuario ou senha invalidos.'
            );
        }

        $this->loginRateLimiter?->registerSuccess(
            $username,
            $clientIp
        );

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

    public function logout(
        ?string $accessTokenValue,
        ?string $refreshTokenValue
    ): void {
        $refreshToken = $this->tryDecodeRefreshToken(
            $refreshTokenValue
        );

        $accessToken = $this->tryDecodeAccessToken(
            $accessTokenValue
        );

        if (
            $refreshToken !== null
            && $refreshToken->familyId !== null
        ) {
            $this->refreshTokens->revokeFamily(
                $refreshToken->userId,
                $refreshToken->familyId
            );
        }

        if ($accessToken !== null) {
            $this->blacklist->add(
                $accessToken,
                TokenRevocationReason::Logout
            );
        }
    }

    private function tryDecodeAccessToken(
        ?string $value
    ): ?TokenClaims {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return $this->jwtService->decodeAccessToken(
                $value
            );
        } catch (InvalidTokenException) {
            return null;
        }
    }

    private function tryDecodeRefreshToken(
        ?string $value
    ): ?TokenClaims {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return $this->jwtService->decodeRefreshToken(
                $value
            );
        } catch (InvalidTokenException) {
            return null;
        }
    }
}
