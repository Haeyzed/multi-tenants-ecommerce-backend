<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Commerce;

/**
 * Sales order lifecycle status.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Fulfilled = 'fulfilled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}
