<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Commerce;

/**
 * Status of an individual order payment / gateway transaction record.
 */
enum OrderPaymentRecordStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Successful = 'successful';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
}
