<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Pos;

enum PosCashMovementType: string
{
    case CashIn = 'cash_in';
    case CashOut = 'cash_out';
    case SaleCash = 'sale_cash';
    case RefundCash = 'refund_cash';
    case Opening = 'opening';
    case Closing = 'closing';
}
