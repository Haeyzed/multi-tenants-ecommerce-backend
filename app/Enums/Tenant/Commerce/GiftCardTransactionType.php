<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Commerce;

/**
 * Ledger entry types for gift card balance movements.
 */
enum GiftCardTransactionType: string
{
    case PurchaseActivate = 'purchase_activate';
    case Redeem = 'redeem';
    case RefundRestore = 'refund_restore';
    case Adjustment = 'adjustment';
}
