<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Commerce;

/**
 * Order fulfillment progress.
 */
enum FulfillmentStatus: string
{
    case Unfulfilled = 'unfulfilled';
    case PartiallyFulfilled = 'partially_fulfilled';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';
}
