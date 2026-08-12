<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\AuthenticationResult;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Http\AuthenticationCookieService;
use App\Http\CsrfRequestValidator;
use App\Http\Request;
use App\Http\Response;
use App\Service\AuthenticationService;

final readonly class AuthenticationController
{
    public function __construct(
        private AuthenticationService $authentication,
        private AuthenticationCookieService $cookies,
        private CsrfRequestValidator $csrf
    ) {
    }

    public function login(Request $request): Response
    {
        $data = $request->json();

        [$username, $password] = $this->credentials($data);

        $result = $this->authentication->login(
            $username,
            $password
        );

        return $this->authenticatedResponse($result);
    }

    public function refresh(Request $request): Response
    {
        $this->csrf->validate($request);

        $refreshToken = $request->cookie(
            AuthenticationCookieService::REFRESH_COOKIE
        ) ?? '';

        $result = $this->authentication->refresh(
            $refreshToken
        );

        return $this->authenticatedResponse($result);
    }

    public function logout(Request $request): Response
    {
        $this->csrf->validate($request);

        $this->authentication->logout(
            $request->cookie(
                AuthenticationCookieService::ACCESS_COOKIE
            ),
            $request->cookie(
                AuthenticationCookieService::REFRESH_COOKIE
            )
        );

        return $this->cookies->clearAuthenticationCookies(
            Response::empty()
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array{0: string, 1: string}
     */
    private function credentials(array $data): array
    {
        $usernameValue = $data['usuario'] ?? null;
        $passwordValue = $data['senha'] ?? null;

        $username = is_string($usernameValue)
            ? mb_strtolower(trim($usernameValue), 'UTF-8')
            : '';

        $password = is_string($passwordValue)
            ? $passwordValue
            : '';

        $errors = [];

        if ($username === '') {
            $errors['usuario'] = 'Informe o usuario.';
        } elseif (
            preg_match(
                '/\A[a-z0-9._-]{3,60}\z/',
                $username
            ) !== 1
        ) {
            $errors['usuario'] = 'Informe um usuario valido.';
        }

        if ($password === '') {
            $errors['senha'] = 'Informe a senha.';
        } elseif (strlen($password) > 72) {
            $errors['senha'] =
                'A senha deve possuir no maximo 72 bytes.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [$username, $password];
    }

    private function authenticatedResponse(
        AuthenticationResult $result
    ): Response {
        $response = Response::json([
            'user' => $this->userData($result->user),
        ]);

        return $this->cookies->addAuthenticationCookies(
            $response,
            $result
        );
    }

    /**
     * @return array<string, int|string>
     */
    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'nome' => $user->name,
            'email' => $user->email,
            'usuario' => $user->username,
            'perfil' => $user->profile->value,
        ];
    }
}
