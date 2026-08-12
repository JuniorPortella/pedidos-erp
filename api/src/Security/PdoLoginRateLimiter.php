<?php

declare(strict_types=1);

namespace App\Security;

use App\Config\AuthConfig;
use App\Exception\TooManyLoginAttemptsException;
use PDO;
use Throwable;

final readonly class PdoLoginRateLimiter implements LoginRateLimiter
{
    private const IP_CONTEXT = 'login_rate_limits.ip';
    private const CREDENTIAL_CONTEXT =
        'login_rate_limits.credential';

    public function __construct(
        private PDO $connection,
        private LookupHasher $hasher,
        private AuthConfig $config
    ) {
    }

    public function assertAllowed(
        string $username,
        string $clientIp
    ): void {
        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT blocked_until
            FROM login_rate_limits
            WHERE key_hash IN (:ip_hash, :credential_hash)
              AND blocked_until > UTC_TIMESTAMP()
            ORDER BY blocked_until DESC
            LIMIT 1
            SQL
        );

        $statement->execute($this->keys($username, $clientIp));
        $blockedUntil = $statement->fetchColumn();

        if ($blockedUntil === false) {
            return;
        }

        $retryAfter = max(
            1,
            strtotime((string) $blockedUntil . ' UTC') - time()
        );

        throw new TooManyLoginAttemptsException($retryAfter);
    }

    public function registerFailure(
        string $username,
        string $clientIp
    ): void {
        $ownsTransaction = !$this->connection->inTransaction();

        if ($ownsTransaction) {
            $this->connection->beginTransaction();
        }

        try {
            $keys = $this->keys($username, $clientIp);

            $this->increment($keys['ip_hash'], 'IP');
            $this->increment(
                $keys['credential_hash'],
                'CREDENTIAL'
            );

            if ($ownsTransaction) {
                $this->connection->commit();
            }
        } catch (Throwable $exception) {
            if (
                $ownsTransaction
                && $this->connection->inTransaction()
            ) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    public function registerSuccess(
        string $username,
        string $clientIp
    ): void {
        $statement = $this->connection->prepare(
            <<<'SQL'
            DELETE FROM login_rate_limits
            WHERE key_hash = :credential_hash
            SQL
        );

        $statement->execute([
            'credential_hash' => $this->credentialHash(
                $username,
                $clientIp
            ),
        ]);
    }

    private function increment(
        string $keyHash,
        string $scope
    ): void {
        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT failure_count, window_started_at
            FROM login_rate_limits
            WHERE key_hash = :key_hash
            FOR UPDATE
            SQL
        );

        $statement->execute(['key_hash' => $keyHash]);
        $current = $statement->fetch();

        if ($current === false) {
            $this->insertFirstFailure($keyHash, $scope);

            return;
        }

        $windowStartedAt = strtotime(
            (string) $current['window_started_at'] . ' UTC'
        );

        $windowExpired = $windowStartedAt === false
            || $windowStartedAt
                <= time() - $this->config->loginWindowSeconds;

        $failureCount = $windowExpired
            ? 1
            : (int) $current['failure_count'] + 1;

        $maximumAttempts = $scope === 'IP'
            ? $this->config->loginIpMaxAttempts
            : $this->config->loginMaxAttempts;

        $blockedUntil = $failureCount >= $maximumAttempts
                ? gmdate(
                    'Y-m-d H:i:s',
                    time() + $this->config->loginBlockSeconds
                )
                : null;

        $update = $this->connection->prepare(
            <<<'SQL'
            UPDATE login_rate_limits
            SET failure_count = :failure_count,
                window_started_at = CASE
                    WHEN :window_expired = 1 THEN UTC_TIMESTAMP()
                    ELSE window_started_at
                END,
                blocked_until = :blocked_until
            WHERE key_hash = :key_hash
            SQL
        );

        $update->execute([
            'failure_count' => $failureCount,
            'window_expired' => $windowExpired ? 1 : 0,
            'blocked_until' => $blockedUntil,
            'key_hash' => $keyHash,
        ]);
    }

    private function insertFirstFailure(
        string $keyHash,
        string $scope
    ): void {
        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO login_rate_limits (
                key_hash,
                scope,
                failure_count,
                window_started_at
            ) VALUES (
                :key_hash,
                :scope,
                1,
                UTC_TIMESTAMP()
            )
            SQL
        );

        $statement->execute([
            'key_hash' => $keyHash,
            'scope' => $scope,
        ]);
    }

    /**
     * @return array{ip_hash: string, credential_hash: string}
     */
    private function keys(
        string $username,
        string $clientIp
    ): array {
        return [
            'ip_hash' => $this->hasher->hash(
                $clientIp,
                self::IP_CONTEXT
            ),
            'credential_hash' => $this->credentialHash(
                $username,
                $clientIp
            ),
        ];
    }

    private function credentialHash(
        string $username,
        string $clientIp
    ): string {
        return $this->hasher->hash(
            mb_strtolower(trim($username), 'UTF-8')
                . "\0"
                . $clientIp,
            self::CREDENTIAL_CONTEXT
        );
    }
}
