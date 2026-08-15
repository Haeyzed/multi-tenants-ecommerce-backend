<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Customer;

/**
 * Customer account status.
 */
enum CustomerStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Blocked = 'blocked';
}
