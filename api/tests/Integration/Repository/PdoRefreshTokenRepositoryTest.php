<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Config\Environment;
use App\Database\ConnectionFactory;
use App\Dto\IssuedToken;
use App\Dto\TokenClaims;
use App\Entity\TokenType;
use App\Exception\RefreshTokenNotActiveException;
use App\Exception\RefreshTokenReuseException;
use App\Repository\PdoRefreshTokenRepository;
use App\Security\LookupHasher;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoRefreshTokenRepositoryTest extends TestCase
{
    private PDO $connection;
    private LookupHasher $hasher;
    private PdoRefreshTokenRepository $repository;
    private int $userId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = ConnectionFactory::create();

        $this->hasher = new LookupHasher(
            Environment::getRequired('DATA_LOOKUP_KEY')
        );

        $this->repository = new PdoRefreshTokenRepository(
            $this->connection,
            $this->hasher
        );

        $this->userId = $this->createTestUser();
    }

    protected function tearDown(): void
    {
        if ($this->connection->inTransaction()) {
            $this->connection->rollBack();
        }

        $statement = $this->connection->prepare(
            'DELETE FROM token_blacklist WHERE usuario_id = :id'
        );
        $statement->execute(['id' => $this->userId]);

        $statement = $this->connection->prepare(
            'DELETE FROM refresh_tokens WHERE usuario_id = :id'
        );
        $statement->execute(['id' => $this->userId]);

        $statement = $this->connection->prepare(
            'DELETE FROM usuarios WHERE id = :id'
        );
        $statement->execute(['id' => $this->userId]);

        parent::tearDown();
    }

    public function testRegistersRefreshTokenWithProtectedIdentifiers(): void
    {
        $refreshToken = $this->createRefreshToken();

        $this->repository->register(
            $this->userId,
            $refreshToken
        );

        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT
                usuario_id,
                jti_hash,
                family_hash,
                replaced_by_jti_hash,
                expires_at,
                used_at,
                revoked_at
            FROM refresh_tokens
            WHERE usuario_id = :usuario_id
            SQL
        );

        $statement->execute([
            'usuario_id' => $this->userId,
        ]);

        $storedToken = $statement->fetch();

        self::assertIsArray($storedToken);
        self::assertSame(
            $this->userId,
            (int) $storedToken['usuario_id']
        );
        self::assertSame(
            $this->hasher->hash(
                $refreshToken->jti,
                'refresh_tokens.jti'
            ),
            $storedToken['jti_hash']
        );
        self::assertSame(
            $this->hasher->hash(
                $refreshToken->familyId,
                'refresh_tokens.family'
            ),
            $storedToken['family_hash']
        );
        self::assertSame(
            gmdate(
                'Y-m-d H:i:s',
                $refreshToken->expiresAt
            ),
            $storedToken['expires_at']
        );
        self::assertNull($storedToken['used_at']);
        self::assertNull($storedToken['revoked_at']);
        self::assertNull(
            $storedToken['replaced_by_jti_hash']
        );
    }

    public function testRotatesRefreshTokenWithinSameFamily(): void
    {
        $current = $this->createRefreshToken();

        $this->repository->register(
            $this->userId,
            $current
        );

        $replacement = $this->createRefreshToken(
            $current->familyId
        );

        $this->repository->rotate(
            $this->createClaims($current),
            $replacement
        );

        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT
                jti_hash,
                family_hash,
                replaced_by_jti_hash,
                used_at,
                revoked_at
            FROM refresh_tokens
            WHERE usuario_id = :usuario_id
            ORDER BY id
            SQL
        );

        $statement->execute([
            'usuario_id' => $this->userId,
        ]);

        $tokens = $statement->fetchAll();

        self::assertCount(2, $tokens);

        $familyHash = $this->hasher->hash(
            $current->familyId,
            'refresh_tokens.family'
        );

        $replacementJtiHash = $this->hasher->hash(
            $replacement->jti,
            'refresh_tokens.jti'
        );

        self::assertSame(
            $familyHash,
            $tokens[0]['family_hash']
        );
        self::assertNotNull($tokens[0]['used_at']);
        self::assertNull($tokens[0]['revoked_at']);
        self::assertSame(
            $replacementJtiHash,
            $tokens[0]['replaced_by_jti_hash']
        );

        self::assertSame(
            $replacementJtiHash,
            $tokens[1]['jti_hash']
        );
        self::assertSame(
            $familyHash,
            $tokens[1]['family_hash']
        );
        self::assertNull($tokens[1]['used_at']);
        self::assertNull($tokens[1]['revoked_at']);
    }

    public function testRevokesFamilyWhenRefreshTokenIsReused(): void
    {
        $current = $this->createRefreshToken();

        $this->repository->register(
            $this->userId,
            $current
        );

        $replacement = $this->createRefreshToken(
            $current->familyId
        );

        $this->repository->rotate(
            $this->createClaims($current),
            $replacement
        );

        $unauthorizedReplacement = $this->createRefreshToken(
            $current->familyId
        );

        try {
            $this->repository->rotate(
                $this->createClaims($current),
                $unauthorizedReplacement
            );

            self::fail(
                'A reutilizacao deveria ter sido detectada.'
            );
        } catch (RefreshTokenReuseException $exception) {
            self::assertSame(
                'Reutilizacao de refresh token detectada.',
                $exception->getMessage()
            );
        }

        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT
                COUNT(*) AS total,
                SUM(revoked_at IS NOT NULL) AS revoked
            FROM refresh_tokens
            WHERE usuario_id = :usuario_id
            SQL
        );

        $statement->execute([
            'usuario_id' => $this->userId,
        ]);

        $result = $statement->fetch();

        self::assertIsArray($result);
        self::assertSame(2, (int) $result['total']);
        self::assertSame(2, (int) $result['revoked']);
        self::assertFalse(
            $this->connection->inTransaction()
        );
    }

    public function testRevokesOneFamilyAndThenAllUserTokens(): void
    {
        $firstFamilyToken = $this->createRefreshToken();
        $secondFamilyToken = $this->createRefreshToken();

        $this->repository->register(
            $this->userId,
            $firstFamilyToken
        );
        $this->repository->register(
            $this->userId,
            $secondFamilyToken
        );

        $this->repository->revokeFamily(
            $this->userId,
            $firstFamilyToken->familyId
        );

        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT revoked_at
            FROM refresh_tokens
            WHERE usuario_id = :usuario_id
            ORDER BY id
            SQL
        );

        $statement->execute([
            'usuario_id' => $this->userId,
        ]);

        $tokens = $statement->fetchAll();

        self::assertCount(2, $tokens);
        self::assertNotNull($tokens[0]['revoked_at']);
        self::assertNull($tokens[1]['revoked_at']);

        $this->repository->revokeAllForUser(
            $this->userId
        );

        $statement->execute([
            'usuario_id' => $this->userId,
        ]);

        $tokens = $statement->fetchAll();

        self::assertCount(2, $tokens);
        self::assertNotNull($tokens[0]['revoked_at']);
        self::assertNotNull($tokens[1]['revoked_at']);
    }

    public function testDeletesOnlyExpiredRefreshTokens(): void
    {
        $expiredToken = $this->createRefreshToken();
        $activeToken = $this->createRefreshToken();

        $this->repository->register(
            $this->userId,
            $expiredToken
        );
        $this->repository->register(
            $this->userId,
            $activeToken
        );

        $expiredJtiHash = $this->hasher->hash(
            $expiredToken->jti,
            'refresh_tokens.jti'
        );

        $statement = $this->connection->prepare(
            <<<'SQL'
            UPDATE refresh_tokens
            SET expires_at = UTC_TIMESTAMP() - INTERVAL 1 SECOND
            WHERE jti_hash = :jti_hash
            SQL
        );

        $statement->execute([
            'jti_hash' => $expiredJtiHash,
        ]);

        self::assertSame(1, $statement->rowCount());
        self::assertSame(1, $this->repository->deleteExpired());

        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT jti_hash
            FROM refresh_tokens
            WHERE usuario_id = :usuario_id
            SQL
        );

        $statement->execute([
            'usuario_id' => $this->userId,
        ]);

        $remainingTokens = $statement->fetchAll();

        self::assertCount(1, $remainingTokens);
        self::assertSame(
            $this->hasher->hash(
                $activeToken->jti,
                'refresh_tokens.jti'
            ),
            $remainingTokens[0]['jti_hash']
        );
    }

    public function testRejectsRevokedTokenWithoutPersistingReplacement(): void
    {
        $current = $this->createRefreshToken();

        $this->repository->register(
            $this->userId,
            $current
        );

        $this->repository->revokeFamily(
            $this->userId,
            $current->familyId
        );

        $replacement = $this->createRefreshToken(
            $current->familyId
        );

        try {
            $this->repository->rotate(
                $this->createClaims($current),
                $replacement
            );

            self::fail(
                'Um refresh token revogado nao pode ser rotacionado.'
            );
        } catch (RefreshTokenNotActiveException $exception) {
            self::assertSame(
                'Refresh token nao esta ativo.',
                $exception->getMessage()
            );
        }

        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT
                COUNT(*) AS total,
                SUM(used_at IS NOT NULL) AS used,
                SUM(revoked_at IS NOT NULL) AS revoked,
                SUM(jti_hash = :replacement_jti_hash) AS replacements
            FROM refresh_tokens
            WHERE usuario_id = :usuario_id
            SQL
        );

        $statement->execute([
            'usuario_id' => $this->userId,
            'replacement_jti_hash' => $this->hasher->hash(
                $replacement->jti,
                'refresh_tokens.jti'
            ),
        ]);

        $result = $statement->fetch();

        self::assertIsArray($result);
        self::assertSame(1, (int) $result['total']);
        self::assertSame(0, (int) $result['used']);
        self::assertSame(1, (int) $result['revoked']);
        self::assertSame(0, (int) $result['replacements']);
        self::assertFalse(
            $this->connection->inTransaction()
        );
    }

    private function createClaims(
        IssuedToken $refreshToken
    ): TokenClaims {
        return new TokenClaims(
            userId: $this->userId,
            jti: $refreshToken->jti,
            type: TokenType::Refresh,
            issuedAt: $refreshToken->issuedAt,
            notBefore: $refreshToken->issuedAt,
            expiresAt: $refreshToken->expiresAt,
            familyId: $refreshToken->familyId
        );
    }

    private function createRefreshToken(
        ?string $familyId = null
    ): IssuedToken {
        $issuedAt = time();

        return new IssuedToken(
            value: 'jwt-de-teste',
            jti: bin2hex(random_bytes(32)),
            type: TokenType::Refresh,
            issuedAt: $issuedAt,
            expiresAt: $issuedAt + 3600,
            familyId: $familyId
                ?? bin2hex(random_bytes(32))
        );
    }

    private function createTestUser(): int
    {
        $suffix = bin2hex(random_bytes(8));

        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO usuarios (
                nome_criptografado,
                email_criptografado,
                email_hash,
                usuario,
                senha_hash,
                perfil
            ) VALUES (
                :nome,
                :email,
                :email_hash,
                :usuario,
                :senha_hash,
                'OPERADOR'
            )
            SQL
        );

        $statement->execute([
            'nome' => base64_encode('Usuario de teste'),
            'email' => base64_encode("{$suffix}@example.com"),
            'email_hash' => hash('sha256', $suffix),
            'usuario' => "refresh_{$suffix}",
            'senha_hash' => password_hash(
                'SenhaSegura123',
                PASSWORD_DEFAULT
            ),
        ]);

        return (int) $this->connection->lastInsertId();
    }
}
