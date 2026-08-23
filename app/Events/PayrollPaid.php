<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\HR\PayrollRun;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a payroll run is marked paid.
 */
class PayrollPaid
{
    use Dispatchable, SerializesModels;

    public function __construct(public PayrollRun $payrollRun) {}
}
