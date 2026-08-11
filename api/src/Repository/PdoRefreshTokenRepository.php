<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\IssuedToken;
use App\Dto\TokenClaims;
use App\Entity\TokenType;
use App\Exception\RefreshTokenNotActiveException;
use App\Exception\RefreshTokenReuseException;
use App\Security\LookupHasher;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class PdoRefreshTokenRepository implements RefreshTokenRepository
{
    private const JTI_CONTEXT = 'refresh_tokens.jti';
    private const FAMILY_CONTEXT = 'refresh_tokens.family';

    public function __construct(
        private readonly PDO $connection,
        private readonly LookupHasher $hasher
    ) {
    }

    public function register(
        int $userId,
        IssuedToken $refreshToken
    ): void {
        if ($userId < 1) {
            throw new InvalidArgumentException(
                'Identificador de usuario invalido.'
            );
        }

        if (
            $refreshToken->type !== TokenType::Refresh
            || $refreshToken->familyId === null
        ) {
            throw new InvalidArgumentException(
                'Refresh token invalido para persistencia.'
            );
        }

        $this->validateIdentifier(
            $refreshToken->jti,
            'JTI do refresh token'
        );

        $this->validateIdentifier(
            $refreshToken->familyId,
            'Familia do refresh token'
        );

        if ($refreshToken->expiresAt <= time()) {
            throw new InvalidArgumentException(
                'Refresh token expirado.'
            );
        }

        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO refresh_tokens (
                usuario_id,
                jti_hash,
                family_hash,
                expires_at
            ) VALUES (
                :usuario_id,
                :jti_hash,
                :family_hash,
                :expires_at
            )
            SQL
        );

        $statement->execute([
            'usuario_id' => $userId,
            'jti_hash' => $this->hasher->hash(
                $refreshToken->jti,
                self::JTI_CONTEXT
            ),
            'family_hash' => $this->hasher->hash(
                $refreshToken->familyId,
                self::FAMILY_CONTEXT
            ),
            'expires_at' => gmdate(
                'Y-m-d H:i:s',
                $refreshToken->expiresAt
            ),
        ]);
    }

    public function rotate(
        TokenClaims $currentToken,
        IssuedToken $replacementToken
    ): void {
        $this->validateRotation(
            $currentToken,
            $replacementToken
        );

        $jtiHash = $this->hasher->hash(
            $currentToken->jti,
            self::JTI_CONTEXT
        );

        $familyHash = $this->hasher->hash(
            $currentToken->familyId,
            self::FAMILY_CONTEXT
        );

        $replacementJtiHash = $this->hasher->hash(
            $replacementToken->jti,
            self::JTI_CONTEXT
        );

        $this->connection->beginTransaction();

        try {
            $storedToken = $this->findForUpdate($jtiHash);

            if (
                $storedToken === null
                || (int) $storedToken['usuario_id']
                    !== $currentToken->userId
                || !hash_equals(
                    $storedToken['family_hash'],
                    $familyHash
                )
                || $this->isExpired($storedToken['expires_at'])
            ) {
                throw new RefreshTokenNotActiveException(
                    'Refresh token nao esta ativo.'
                );
            }

            if ($storedToken['used_at'] !== null) {
                $this->revokeFamilyByHash(
                    $currentToken->userId,
                    $familyHash
                );

                $this->connection->commit();

                throw new RefreshTokenReuseException(
                    'Reutilizacao de refresh token detectada.'
                );
            }

            if ($storedToken['revoked_at'] !== null) {
                throw new RefreshTokenNotActiveException(
                    'Refresh token nao esta ativo.'
                );
            }

            $this->markAsUsed(
                (int) $storedToken['id'],
                $replacementJtiHash
            );

            $this->register(
                $currentToken->userId,
                $replacementToken
            );

            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    public function revokeFamily(
        int $userId,
        string $familyId
    ): void {
        if ($userId < 1) {
            throw new InvalidArgumentException(
                'Identificador de usuario invalido.'
            );
        }

        $this->validateIdentifier(
            $familyId,
            'Familia do refresh token'
        );

        $this->revokeFamilyByHash(
            $userId,
            $this->hasher->hash(
                $familyId,
                self::FAMILY_CONTEXT
            )
        );
    }

    public function revokeAllForUser(int $userId): void
    {
        if ($userId < 1) {
            throw new InvalidArgumentException(
                'Identificador de usuario invalido.'
            );
        }

        $statement = $this->connection->prepare(
            <<<'SQL'
            UPDATE refresh_tokens
            SET revoked_at = COALESCE(
                revoked_at,
                UTC_TIMESTAMP()
            )
            WHERE usuario_id = :usuario_id
            SQL
        );

        $statement->execute([
            'usuario_id' => $userId,
        ]);
    }

    public function deleteExpired(): int
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            DELETE FROM refresh_tokens
            WHERE expires_at <= UTC_TIMESTAMP()
            SQL
        );

        $statement->execute();

        return $statement->rowCount();
    }

    private function validateRotation(
        TokenClaims $currentToken,
        IssuedToken $replacementToken
    ): void {
        if (
            $currentToken->type !== TokenType::Refresh
            || $currentToken->familyId === null
            || $replacementToken->type !== TokenType::Refresh
            || $replacementToken->familyId === null
        ) {
            throw new InvalidArgumentException(
                'Dados de rotacao invalidos.'
            );
        }

        if ($currentToken->userId < 1) {
            throw new InvalidArgumentException(
                'Identificador de usuario invalido.'
            );
        }

        $this->validateIdentifier(
            $currentToken->jti,
            'JTI atual'
        );

        $this->validateIdentifier(
            $currentToken->familyId,
            'Familia atual'
        );

        $this->validateIdentifier(
            $replacementToken->jti,
            'JTI substituto'
        );

        $this->validateIdentifier(
            $replacementToken->familyId,
            'Familia substituta'
        );

        if (
            !hash_equals(
                $currentToken->familyId,
                $replacementToken->familyId
            )
            || hash_equals(
                $currentToken->jti,
                $replacementToken->jti
            )
        ) {
            throw new InvalidArgumentException(
                'Rotacao de refresh token invalida.'
            );
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findForUpdate(string $jtiHash): ?array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT
                id,
                usuario_id,
                family_hash,
                expires_at,
                used_at,
                revoked_at
            FROM refresh_tokens
            WHERE jti_hash = :jti_hash
            FOR UPDATE
            SQL
        );

        $statement->execute([
            'jti_hash' => $jtiHash,
        ]);

        $storedToken = $statement->fetch();

        return $storedToken === false
            ? null
            : $storedToken;
    }

    private function isExpired(string $expiresAt): bool
    {
        $expiration = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $expiresAt,
            new DateTimeZone('UTC')
        );

        if ($expiration === false) {
            throw new RuntimeException(
                'Data de expiracao do refresh token invalida.'
            );
        }

        return $expiration->getTimestamp() <= time();
    }

    private function revokeFamilyByHash(
        int $userId,
        string $familyHash
    ): void {
        $statement = $this->connection->prepare(
            <<<'SQL'
            UPDATE refresh_tokens
            SET revoked_at = COALESCE(
                revoked_at,
                UTC_TIMESTAMP()
            )
            WHERE usuario_id = :usuario_id
              AND family_hash = :family_hash
            SQL
        );

        $statement->execute([
            'usuario_id' => $userId,
            'family_hash' => $familyHash,
        ]);
    }

    private function markAsUsed(
        int $tokenId,
        string $replacementJtiHash
    ): void {
        $statement = $this->connection->prepare(
            <<<'SQL'
            UPDATE refresh_tokens
            SET
                used_at = UTC_TIMESTAMP(),
                replaced_by_jti_hash = :replacement_jti_hash
            WHERE id = :id
              AND used_at IS NULL
              AND revoked_at IS NULL
            SQL
        );

        $statement->execute([
            'id' => $tokenId,
            'replacement_jti_hash' => $replacementJtiHash,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(
                'Nao foi possivel rotacionar o refresh token.'
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
                sprintf('%s invalido.', $name)
            );
        }
    }
}
