<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Driver;

/**
 * Driver account status.
 */
enum DriverStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Blocked = 'blocked';
}
