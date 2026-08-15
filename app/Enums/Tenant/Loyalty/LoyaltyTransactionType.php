<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Loyalty;

/**
 * Ledger entry kinds for loyalty point movements.
 */
enum LoyaltyTransactionType: string
{
    case Earn = 'earn';
    case Redeem = 'redeem';
    case Expire = 'expire';
    case Adjustment = 'adjustment';
    case RefundReversal = 'refund_reversal';
}
