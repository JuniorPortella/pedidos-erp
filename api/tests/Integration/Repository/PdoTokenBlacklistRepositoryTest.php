<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Config\Environment;
use App\Database\ConnectionFactory;
use App\Dto\TokenClaims;
use App\Entity\TokenRevocationReason;
use App\Entity\TokenType;
use App\Repository\PdoTokenBlacklistRepository;
use App\Security\LookupHasher;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoTokenBlacklistRepositoryTest extends TestCase
{
    private PDO $connection;
    private LookupHasher $hasher;
    private PdoTokenBlacklistRepository $repository;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = ConnectionFactory::create();
        $this->connection->beginTransaction();

        $this->hasher = new LookupHasher(
            Environment::getRequired('DATA_LOOKUP_KEY')
        );

        $this->repository = new PdoTokenBlacklistRepository(
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

        parent::tearDown();
    }

    public function testAddsTokenWithProtectedJtiIdempotently(): void
    {
        $token = $this->createClaims();

        self::assertFalse(
            $this->repository->contains($token->jti)
        );

        $this->repository->add(
            $token,
            TokenRevocationReason::Logout
        );

        self::assertTrue(
            $this->repository->contains($token->jti)
        );

        $this->repository->add(
            $token,
            TokenRevocationReason::AdminRevoked
        );

        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT
                usuario_id,
                jti_hash,
                token_type,
                motivo,
                expires_at
            FROM token_blacklist
            WHERE usuario_id = :usuario_id
            SQL
        );

        $statement->execute([
            'usuario_id' => $this->userId,
        ]);

        $tokens = $statement->fetchAll();

        self::assertCount(1, $tokens);
        self::assertSame(
            $this->userId,
            (int) $tokens[0]['usuario_id']
        );
        self::assertSame(
            $this->hasher->hash(
                $token->jti,
                'token_blacklist.jti'
            ),
            $tokens[0]['jti_hash']
        );
        self::assertSame(
            TokenType::Access->value,
            $tokens[0]['token_type']
        );
        self::assertSame(
            TokenRevocationReason::Logout->value,
            $tokens[0]['motivo']
        );
        self::assertSame(
            gmdate('Y-m-d H:i:s', $token->expiresAt),
            $tokens[0]['expires_at']
        );
    }

    public function testIgnoresAndDeletesExpiredBlacklistEntries(): void
    {
        $expiredToken = $this->createClaims();
        $activeToken = $this->createClaims();

        $this->repository->add(
            $expiredToken,
            TokenRevocationReason::Logout
        );
        $this->repository->add(
            $activeToken,
            TokenRevocationReason::AdminRevoked
        );

        $statement = $this->connection->prepare(
            <<<'SQL'
            UPDATE token_blacklist
            SET expires_at = UTC_TIMESTAMP() - INTERVAL 1 SECOND
            WHERE jti_hash = :jti_hash
            SQL
        );

        $statement->execute([
            'jti_hash' => $this->hasher->hash(
                $expiredToken->jti,
                'token_blacklist.jti'
            ),
        ]);

        self::assertFalse(
            $this->repository->contains($expiredToken->jti)
        );
        self::assertTrue(
            $this->repository->contains($activeToken->jti)
        );
        self::assertSame(
            1,
            $this->repository->deleteExpired()
        );

        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT COUNT(*)
            FROM token_blacklist
            WHERE usuario_id = :usuario_id
            SQL
        );

        $statement->execute([
            'usuario_id' => $this->userId,
        ]);

        self::assertSame(
            1,
            (int) $statement->fetchColumn()
        );
        self::assertTrue(
            $this->repository->contains($activeToken->jti)
        );
    }

    private function createClaims(): TokenClaims
    {
        $issuedAt = time();

        return new TokenClaims(
            userId: $this->userId,
            jti: bin2hex(random_bytes(32)),
            type: TokenType::Access,
            issuedAt: $issuedAt,
            notBefore: $issuedAt,
            expiresAt: $issuedAt + 3600,
            csrfHash: hash('sha256', 'csrf-de-teste')
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
            'usuario' => "blacklist_{$suffix}",
            'senha_hash' => password_hash(
                'SenhaSegura123',
                PASSWORD_DEFAULT
            ),
        ]);

        return (int) $this->connection->lastInsertId();
    }
}
