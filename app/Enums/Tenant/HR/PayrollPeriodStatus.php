<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Open periods can still receive a payroll run.
 */
enum PayrollPeriodStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
