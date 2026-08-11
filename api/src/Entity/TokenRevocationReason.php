<?php

declare(strict_types=1);

namespace App\Entity;

enum TokenRevocationReason: string
{
    case Logout = 'LOGOUT';
    case ReuseDetected = 'REUSE_DETECTED';
    case UserDisabled = 'USER_DISABLED';
    case PasswordChanged = 'PASSWORD_CHANGED';
    case AdminRevoked = 'ADMIN_REVOKED';
}
