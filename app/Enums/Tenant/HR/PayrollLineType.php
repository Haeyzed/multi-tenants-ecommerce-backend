<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Payslip line classification.
 */
enum PayrollLineType: string
{
    case Earning = 'earning';
    case Deduction = 'deduction';
}
