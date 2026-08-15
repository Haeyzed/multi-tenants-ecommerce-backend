<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Marketplace;

/**
 * Commission accrual and payout lifecycle.
 */
enum SellerCommissionStatus: string
{
    case Pending = 'pending';
    case Earned = 'earned';
    case Paid = 'paid';
    case Reversed = 'reversed';
}
