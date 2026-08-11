<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class MethodNotAllowedException extends RuntimeException
{
    /**
     * @param list<string> $allowedMethods
     */
    public function __construct(
        public readonly array $allowedMethods
    ) {
        parent::__construct(
            'Metodo HTTP nao permitido.'
        );
    }
}
