<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Commerce;

/**
 * Availability of a customer store credit account.
 */
enum StoreCreditAccountStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';
}
