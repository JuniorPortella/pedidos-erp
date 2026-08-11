<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\TokenClaims;
use App\Entity\TokenRevocationReason;
use App\Security\LookupHasher;
use InvalidArgumentException;
use PDO;

final class PdoTokenBlacklistRepository
    implements TokenBlacklistRepository
{
    private const JTI_CONTEXT = 'token_blacklist.jti';

    public function __construct(
        private readonly PDO $connection,
        private readonly LookupHasher $hasher
    ) {
    }

    public function add(
        TokenClaims $token,
        TokenRevocationReason $reason
    ): void {
        if ($token->userId < 1) {
            throw new InvalidArgumentException(
                'Identificador de usuario invalido.'
            );
        }

        $this->validateJti($token->jti);

        if ($token->expiresAt <= time()) {
            throw new InvalidArgumentException(
                'Nao e necessario bloquear um token expirado.'
            );
        }

        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO token_blacklist (
                usuario_id,
                jti_hash,
                token_type,
                motivo,
                expires_at
            ) VALUES (
                :usuario_id,
                :jti_hash,
                :token_type,
                :motivo,
                :expires_at
            )
            ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id)
            SQL
        );

        $statement->execute([
            'usuario_id' => $token->userId,
            'jti_hash' => $this->hasher->hash(
                $token->jti,
                self::JTI_CONTEXT
            ),
            'token_type' => $token->type->value,
            'motivo' => $reason->value,
            'expires_at' => gmdate(
                'Y-m-d H:i:s',
                $token->expiresAt
            ),
        ]);
    }

    public function contains(string $jti): bool
    {
        $this->validateJti($jti);

        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT EXISTS (
                SELECT 1
                FROM token_blacklist
                WHERE jti_hash = :jti_hash
                  AND expires_at > UTC_TIMESTAMP()
            )
            SQL
        );

        $statement->execute([
            'jti_hash' => $this->hasher->hash(
                $jti,
                self::JTI_CONTEXT
            ),
        ]);

        return (bool) $statement->fetchColumn();
    }

    public function deleteExpired(): int
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            DELETE FROM token_blacklist
            WHERE expires_at <= UTC_TIMESTAMP()
            SQL
        );

        $statement->execute();

        return $statement->rowCount();
    }

    private function validateJti(string $jti): void
    {
        if (
            preg_match(
                '/\A[a-f0-9]{64}\z/',
                $jti
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'JTI do token invalido.'
            );
        }
    }
}
