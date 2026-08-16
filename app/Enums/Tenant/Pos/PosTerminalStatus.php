<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Pos;

enum PosTerminalStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
