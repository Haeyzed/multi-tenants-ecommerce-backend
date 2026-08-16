<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Commerce;

/**
 * Computed flash sale lifecycle status from schedule and is_active.
 */
enum FlashSaleStatus: string
{
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Ended = 'ended';
    case Inactive = 'inactive';
}
