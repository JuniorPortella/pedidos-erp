<?php

declare(strict_types=1);

namespace App\Service;

final class PhoneNormalizer
{
    private function __construct()
    {
    }

    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (!is_string($digits)) {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 10 || strlen($digits) === 11) {
            return '55' . $digits;
        }

        return $digits;
    }
}
