<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\CsrfTokenService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CsrfTokenServiceTest extends TestCase
{
    private CsrfTokenService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CsrfTokenService();
    }

    public function testGeneratesRandomTokens(): void
    {
        $first = $this->service->generate();
        $second = $this->service->generate();

        self::assertMatchesRegularExpression(
            '/\A[a-f0-9]{64}\z/',
            $first
        );
        self::assertMatchesRegularExpression(
            '/\A[a-f0-9]{64}\z/',
            $second
        );
        self::assertNotSame($first, $second);
    }

    public function testHashesAndVerifiesToken(): void
    {
        $token = $this->service->generate();
        $hash = $this->service->hash($token);

        self::assertMatchesRegularExpression(
            '/\A[a-f0-9]{64}\z/',
            $hash
        );
        self::assertTrue(
            $this->service->verify($token, $hash)
        );
        self::assertFalse(
            $this->service->verify(
                $this->service->generate(),
                $hash
            )
        );
    }

    public function testRejectsMalformedValues(): void
    {
        self::assertFalse(
            $this->service->verify('invalido', 'invalido')
        );

        $this->expectException(
            InvalidArgumentException::class
        );
        $this->expectExceptionMessage(
            'Token CSRF invalido.'
        );

        $this->service->hash('invalido');
    }
}
