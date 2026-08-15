<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Commerce;

/**
 * Checkout session lifecycle status.
 */
enum CheckoutSessionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
