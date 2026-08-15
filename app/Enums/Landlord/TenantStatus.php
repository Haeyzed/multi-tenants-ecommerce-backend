<?php

declare(strict_types=1);

namespace App\Enums\Landlord;

/**
 * Lifecycle status for a platform tenant.
 */
enum TenantStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
    case Pending = 'pending';
}
