<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\PayrollRun;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a payroll run is processed (locked) or submitted for approval.
 */
class PayrollProcessed
{
    use Dispatchable, SerializesModels;

    public function __construct(public PayrollRun $payrollRun) {}
}
