<?php

declare(strict_types=1);

namespace Tests\Integration\Security;

use App\Config\AuthConfig;
use App\Config\Environment;
use App\Database\ConnectionFactory;
use App\Exception\TooManyLoginAttemptsException;
use App\Security\LookupHasher;
use App\Security\PdoLoginRateLimiter;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoLoginRateLimiterTest extends TestCase
{
    private const VARIABLES = [
        'AUTH_LOGIN_MAX_ATTEMPTS',
        'AUTH_LOGIN_IP_MAX_ATTEMPTS',
        'AUTH_LOGIN_WINDOW',
        'AUTH_LOGIN_BLOCK',
    ];

    private PDO $connection;
    private LookupHasher $hasher;
    private PdoLoginRateLimiter $rateLimiter;

    /** @var array<string, string|false> */
    private array $originalValues = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::VARIABLES as $name) {
            $this->originalValues[$name] = getenv($name);
        }

        putenv('AUTH_LOGIN_MAX_ATTEMPTS=2');
        putenv('AUTH_LOGIN_IP_MAX_ATTEMPTS=10');
        putenv('AUTH_LOGIN_WINDOW=900');
        putenv('AUTH_LOGIN_BLOCK=120');

        $this->connection = ConnectionFactory::create();
        $this->connection->beginTransaction();

        $this->hasher = new LookupHasher(
            Environment::getRequired('DATA_LOOKUP_KEY')
        );

        $this->rateLimiter = new PdoLoginRateLimiter(
            $this->connection,
            $this->hasher,
            AuthConfig::fromEnvironment()
        );
    }

    protected function tearDown(): void
    {
        if ($this->connection->inTransaction()) {
            $this->connection->rollBack();
        }

        foreach ($this->originalValues as $name => $value) {
            if ($value === false) {
                putenv($name);
            } else {
                putenv($name . '=' . $value);
            }
        }

        parent::tearDown();
    }

    public function testBlocksCredentialAfterConfiguredFailures(): void
    {
        $username = 'usuario_' . bin2hex(random_bytes(4));
        $clientIp = '203.0.113.10';

        $this->rateLimiter->registerFailure(
            $username,
            $clientIp
        );
        $this->rateLimiter->assertAllowed(
            $username,
            $clientIp
        );

        $this->rateLimiter->registerFailure(
            $username,
            $clientIp
        );

        $this->expectException(
            TooManyLoginAttemptsException::class
        );

        $this->rateLimiter->assertAllowed(
            $username,
            $clientIp
        );
    }

    public function testStoresOnlyProtectedKeysAndClearsCredentialOnSuccess(): void
    {
        $username = 'usuario_' . bin2hex(random_bytes(4));
        $clientIp = '198.51.100.25';

        $this->rateLimiter->registerFailure(
            $username,
            $clientIp
        );

        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT key_hash, scope
            FROM login_rate_limits
            WHERE key_hash IN (:ip_hash, :credential_hash)
            SQL
        );
        $statement->execute([
            'ip_hash' => $this->hasher->hash(
                $clientIp,
                'login_rate_limits.ip'
            ),
            'credential_hash' => $this->hasher->hash(
                mb_strtolower($username, 'UTF-8')
                    . "\0"
                    . $clientIp,
                'login_rate_limits.credential'
            ),
        ]);

        $rows = $statement->fetchAll();
        self::assertCount(2, $rows);

        foreach ($rows as $row) {
            self::assertMatchesRegularExpression(
                '/\A[a-f0-9]{64}\z/',
                (string) $row['key_hash']
            );
            self::assertNotSame($username, $row['key_hash']);
            self::assertNotSame($clientIp, $row['key_hash']);
        }

        $this->rateLimiter->registerSuccess(
            $username,
            $clientIp
        );

        $statement->execute([
            'ip_hash' => $this->hasher->hash(
                $clientIp,
                'login_rate_limits.ip'
            ),
            'credential_hash' => $this->hasher->hash(
                mb_strtolower($username, 'UTF-8')
                    . "\0"
                    . $clientIp,
                'login_rate_limits.credential'
            ),
        ]);
        $scopes = $statement->fetchAll(PDO::FETCH_COLUMN, 1);

        self::assertSame(['IP'], $scopes);
    }
}
