<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Marketplace;

/**
 * Seller payout processing status.
 */
enum SellerPayoutStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Paid = 'paid';
    case Failed = 'failed';
}
