<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class TooManyLoginAttemptsException extends RuntimeException
{
    public function __construct(
        public readonly int $retryAfter
    ) {
        parent::__construct(
            'Muitas tentativas de login. Tente novamente mais tarde.'
        );
    }
}
