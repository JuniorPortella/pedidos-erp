<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Dto\AuthenticatedUser;
use App\Exception\InvalidTokenException;
use App\Exception\UnauthenticatedException;
use App\Http\AuthenticationCookieService;
use App\Http\Request;
use App\Http\Response;
use App\Repository\TokenBlacklistRepository;
use App\Repository\UserRepository;
use App\Service\JwtService;

final readonly class AccessTokenMiddleware
{
    public function __construct(
        private JwtService $jwtService,
        private TokenBlacklistRepository $blacklist,
        private UserRepository $users
    ) {
    }

    public function authenticate(
        Request $request
    ): AuthenticatedUser {
        $tokenValue = $request->cookie(
            AuthenticationCookieService::ACCESS_COOKIE
        );

        if ($tokenValue === null || $tokenValue === '') {
            throw new UnauthenticatedException(
                'Access token nao informado.'
            );
        }

        try {
            $token = $this->jwtService->decodeAccessToken(
                $tokenValue
            );
        } catch (InvalidTokenException $exception) {
            throw new UnauthenticatedException(
                'Access token invalido ou expirado.',
                0,
                $exception
            );
        }

        if ($this->blacklist->contains($token->jti)) {
            throw new UnauthenticatedException(
                'Access token revogado.'
            );
        }

        $user = $this->users->findById($token->userId);

        if (
            $user === null
            || !$user->active
            || $user->isDeleted()
        ) {
            throw new UnauthenticatedException(
                'Usuario da sessao nao esta ativo.'
            );
        }

        return new AuthenticatedUser(
            user: $user,
            token: $token
        );
    }

    /**
     * @param callable(AuthenticatedUser): Response $next
     */
    public function handle(
        Request $request,
        callable $next
    ): Response {
        return $next($this->authenticate($request));
    }
}
