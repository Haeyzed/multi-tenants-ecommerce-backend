<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Pos;

enum SalesChannel: string
{
    case Online = 'online';
    case Pos = 'pos';
}
