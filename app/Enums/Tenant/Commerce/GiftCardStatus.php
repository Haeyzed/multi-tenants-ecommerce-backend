<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Commerce;

/**
 * Lifecycle of a gift card.
 */
enum GiftCardStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Expired = 'expired';
    case Depleted = 'depleted';
    case Cancelled = 'cancelled';
}
