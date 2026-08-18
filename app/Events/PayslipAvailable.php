<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\PayrollItem;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a payslip becomes available to an employee.
 */
class PayslipAvailable
{
    use Dispatchable, SerializesModels;

    public function __construct(public PayrollItem $payrollItem) {}
}
