<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * How a salary component amount is interpreted.
 */
enum SalaryComponentCalculation: string
{
    case Fixed = 'fixed';
    case Percent = 'percent';
}
