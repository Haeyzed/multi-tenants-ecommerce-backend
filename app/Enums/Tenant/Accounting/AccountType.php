<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Accounting;

/**
 * Chart of accounts classification.
 */
enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';
}
