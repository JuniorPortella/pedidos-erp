<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\PhoneNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneNormalizerTest extends TestCase
{
    #[DataProvider('equivalentPhones')]
    public function testNormalizesEquivalentPhones(
        string $phone,
        string $expected
    ): void {
        self::assertSame(
            $expected,
            PhoneNormalizer::normalize($phone)
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function equivalentPhones(): array
    {
        return [
            'formatted Brazil number' => [
                '+55 (11) 99999-9999',
                '5511999999999',
            ],
            'Brazil number without country code' => [
                '11999999999',
                '5511999999999',
            ],
            'international prefix' => [
                '00 351 912 345 678',
                '351912345678',
            ],
        ];
    }
}
