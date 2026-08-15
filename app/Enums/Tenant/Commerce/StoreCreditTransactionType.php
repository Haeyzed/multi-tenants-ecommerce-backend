<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Commerce;

/**
 * Ledger entry types for store credit balance movements.
 */
enum StoreCreditTransactionType: string
{
    case Credit = 'credit';
    case Debit = 'debit';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
    case Expire = 'expire';
}
