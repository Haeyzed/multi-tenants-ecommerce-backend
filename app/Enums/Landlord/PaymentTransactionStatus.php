<?php

declare(strict_types=1);

namespace App\Enums\Landlord;

/**
 * Provider-agnostic payment transaction status.
 */
enum PaymentTransactionStatus: string
{
    case Pending = 'pending';
    case Successful = 'successful';
    case Failed = 'failed';
    case Abandoned = 'abandoned';
    case Refunded = 'refunded';
}
