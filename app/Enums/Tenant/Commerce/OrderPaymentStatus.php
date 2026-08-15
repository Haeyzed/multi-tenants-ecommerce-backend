<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Commerce;

/**
 * Aggregate payment status on an order.
 */
enum OrderPaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
}
