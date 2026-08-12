<?php

declare(strict_types=1);

namespace App\Entity;

enum OrderStatus: string
{
    case Pending = 'PENDENTE';
    case Processing = 'EM_PROCESSAMENTO';
    case Completed = 'CONCLUIDO';
}
