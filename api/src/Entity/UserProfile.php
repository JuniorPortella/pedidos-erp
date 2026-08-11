<?php

declare(strict_types=1);

namespace App\Entity;

enum UserProfile: string
{
    case Admin = 'ADMIN';
    case Operator = 'OPERADOR';
}
