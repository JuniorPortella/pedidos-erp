<?php

declare(strict_types=1);

namespace App\Service;

use App\Config\AuthConfig;
use App\Dto\IssuedToken;
use App\Dto\TokenClaims;
use App\Entity\TokenType;
use App\Exception\InvalidTokenException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;
use Throwable;

final class JwtService
{
    private const ALGORITHM = 'HS256';

    public function __construct(
        private readonly AuthConfig $config
    ) {
    }

    public function issueAccessToken(
        int $userId,
        string $csrfHash
    ): IssuedToken {
        $this->validateUserId($userId);
        $this->validateIdentifier(
            $csrfHash,
            'Hash CSRF'
        );

        return $this->issue(
            userId: $userId,
            type: TokenType::Access,
            additionalClaims: [
                'csrf_hash' => $csrfHash,
            ]
        );
    }

    public function issueRefreshToken(
        int $userId,
        ?string $familyId = null
    ): IssuedToken {
        $this->validateUserId($userId);

        $familyId ??= self::generateIdentifier();

        $this->validateIdentifier(
            $familyId,
            'Familia do refresh token'
        );

        return $this->issue(
            userId: $userId,
            type: TokenType::Refresh,
            additionalClaims: [
                'family_id' => $familyId,
            ],
            familyId: $familyId
        );
    }

    public function decodeAccessToken(
        string $value
    ): TokenClaims {
        return $this->decode(
            $value,
            TokenType::Access
        );
    }

    public function decodeRefreshToken(
        string $value
    ): TokenClaims {
        return $this->decode(
            $value,
            TokenType::Refresh
        );
    }

    private function decode(
        string $value,
        TokenType $expectedType
    ): TokenClaims {
        if ($value === '') {
            throw new InvalidTokenException(
                'Token invalido ou expirado.'
            );
        }

        $secret = $expectedType === TokenType::Access
            ? $this->config->accessSecret
            : $this->config->refreshSecret;

        try {
            $decoded = JWT::decode(
                $value,
                new Key(
                    $secret,
                    self::ALGORITHM
                )
            );
        } catch (Throwable $exception) {
            throw new InvalidTokenException(
                'Token invalido ou expirado.',
                0,
                $exception
            );
        }

        $claims = get_object_vars($decoded);

        if (
            ($claims['iss'] ?? null)
                !== $this->config->issuer
            || ($claims['aud'] ?? null)
                !== $this->config->audience
        ) {
            throw new InvalidTokenException(
                'Token invalido ou expirado.'
            );
        }

        $type = TokenType::tryFrom(
            is_string($claims['token_type'] ?? null)
                ? $claims['token_type']
                : ''
        );

        if ($type !== $expectedType) {
            throw new InvalidTokenException(
                'Token invalido ou expirado.'
            );
        }

        $subject = $claims['sub'] ?? null;
        $jti = $claims['jti'] ?? null;
        $issuedAt = $claims['iat'] ?? null;
        $notBefore = $claims['nbf'] ?? null;
        $expiresAt = $claims['exp'] ?? null;

        if (
            !is_string($subject)
            || preg_match(
                '/\A[1-9][0-9]*\z/',
                $subject
            ) !== 1
            || !is_string($jti)
            || preg_match(
                '/\A[a-f0-9]{64}\z/',
                $jti
            ) !== 1
            || !is_int($issuedAt)
            || !is_int($notBefore)
            || !is_int($expiresAt)
            || $notBefore < $issuedAt
            || $expiresAt <= $notBefore
        ) {
            throw new InvalidTokenException(
                'Token invalido ou expirado.'
            );
        }

        $familyId = null;
        $csrfHash = null;

        if ($type === TokenType::Access) {
            $csrfHash = $claims['csrf_hash'] ?? null;

            if (
                !is_string($csrfHash)
                || preg_match(
                    '/\A[a-f0-9]{64}\z/',
                    $csrfHash
                ) !== 1
            ) {
                throw new InvalidTokenException(
                    'Token invalido ou expirado.'
                );
            }
        }

        if ($type === TokenType::Refresh) {
            $familyId = $claims['family_id'] ?? null;

            if (
                !is_string($familyId)
                || preg_match(
                    '/\A[a-f0-9]{64}\z/',
                    $familyId
                ) !== 1
            ) {
                throw new InvalidTokenException(
                    'Token invalido ou expirado.'
                );
            }
        }

        return new TokenClaims(
            userId: (int) $subject,
            jti: $jti,
            type: $type,
            issuedAt: $issuedAt,
            notBefore: $notBefore,
            expiresAt: $expiresAt,
            familyId: $familyId,
            csrfHash: $csrfHash
        );
    }

    /**
     * @param array<string, string> $additionalClaims
     */
    private function issue(
        int $userId,
        TokenType $type,
        array $additionalClaims,
        ?string $familyId = null
    ): IssuedToken {
        $issuedAt = time();

        $ttl = $type === TokenType::Access
            ? $this->config->accessTtl
            : $this->config->refreshTtl;

        $secret = $type === TokenType::Access
            ? $this->config->accessSecret
            : $this->config->refreshSecret;

        $expiresAt = $issuedAt + $ttl;
        $jti = self::generateIdentifier();

        $payload = array_merge(
            [
                'iss' => $this->config->issuer,
                'aud' => $this->config->audience,
                'iat' => $issuedAt,
                'nbf' => $issuedAt,
                'exp' => $expiresAt,
                'sub' => (string) $userId,
                'jti' => $jti,
                'token_type' => $type->value,
            ],
            $additionalClaims
        );

        $value = JWT::encode(
            $payload,
            $secret,
            self::ALGORITHM
        );

        return new IssuedToken(
            value: $value,
            jti: $jti,
            type: $type,
            issuedAt: $issuedAt,
            expiresAt: $expiresAt,
            familyId: $familyId
        );
    }

    private function validateUserId(int $userId): void
    {
        if ($userId < 1) {
            throw new InvalidArgumentException(
                'Identificador de usuario invalido.'
            );
        }
    }

    private function validateIdentifier(
        string $identifier,
        string $name
    ): void {
        if (
            preg_match(
                '/\A[a-f0-9]{64}\z/',
                $identifier
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    '%s invalido.',
                    $name
                )
            );
        }
    }

    private static function generateIdentifier(): string
    {
        return bin2hex(random_bytes(32));
    }
}
