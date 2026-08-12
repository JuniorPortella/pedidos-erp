<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class ClientInput
{
    public function __construct(
        public string $name,
        public string $phone
    ) {
    }
}
