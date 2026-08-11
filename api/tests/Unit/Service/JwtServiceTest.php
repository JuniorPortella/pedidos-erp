<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Config\AuthConfig;
use App\Entity\TokenType;
use App\Exception\InvalidTokenException;
use App\Service\JwtService;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;

final class JwtServiceTest extends TestCase
{
    private AuthConfig $config;
    private JwtService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = AuthConfig::fromEnvironment();
        $this->service = new JwtService($this->config);
    }

    protected function tearDown(): void
    {
        JWT::$timestamp = null;

        parent::tearDown();
    }

    public function testIssuesAndDecodesAccessToken(): void
    {
        $csrfHash = hash('sha256', 'csrf-de-teste');

        $issued = $this->service->issueAccessToken(10, $csrfHash);
        $claims = $this->service->decodeAccessToken($issued->value);

        self::assertSame(TokenType::Access, $issued->type);
        self::assertSame(10, $claims->userId);
        self::assertSame($issued->jti, $claims->jti);
        self::assertSame($csrfHash, $claims->csrfHash);
        self::assertNull($claims->familyId);
        self::assertSame(
            $this->config->accessTtl,
            $issued->expiresAt - $issued->issuedAt
        );
    }

    public function testIssuesAndDecodesRefreshToken(): void
    {
        $issued = $this->service->issueRefreshToken(10);
        $claims = $this->service->decodeRefreshToken($issued->value);

        self::assertSame(TokenType::Refresh, $issued->type);
        self::assertSame(10, $claims->userId);
        self::assertSame($issued->jti, $claims->jti);
        self::assertSame($issued->familyId, $claims->familyId);
        self::assertNull($claims->csrfHash);
        self::assertSame(
            $this->config->refreshTtl,
            $issued->expiresAt - $issued->issuedAt
        );
    }

    public function testPreservesFamilyWhenRotatingRefreshToken(): void
    {
        $first = $this->service->issueRefreshToken(10);
        $second = $this->service->issueRefreshToken(
            10,
            $first->familyId
        );

        self::assertNotSame($first->jti, $second->jti);
        self::assertSame($first->familyId, $second->familyId);
    }

    public function testRejectsAccessTokenAsRefreshToken(): void
    {
        $access = $this->service->issueAccessToken(
            10,
            hash('sha256', 'csrf-de-teste')
        );

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('Token invalido ou expirado.');

        $this->service->decodeRefreshToken($access->value);
    }

    public function testRejectsTamperedToken(): void
    {
        $access = $this->service->issueAccessToken(
            10,
            hash('sha256', 'csrf-de-teste')
        );

        $parts = explode('.', $access->value);
        $parts[2][0] = $parts[2][0] === 'a' ? 'b' : 'a';

        $tamperedToken = implode('.', $parts);

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('Token invalido ou expirado.');

        $this->service->decodeAccessToken($tamperedToken);
    }

    public function testRejectsExpiredToken(): void
    {
        $access = $this->service->issueAccessToken(
            10,
            hash('sha256', 'csrf-de-teste')
        );

        JWT::$timestamp = $access->expiresAt + 1;

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('Token invalido ou expirado.');

        $this->service->decodeAccessToken($access->value);
    }
}
