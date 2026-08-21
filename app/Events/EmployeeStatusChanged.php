<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\Tenant\HR\EmploymentStatus;
use App\Models\HR\Employee;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an employee's employment status changes.
 */
class EmployeeStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public EmploymentStatus $previous,
        public EmploymentStatus $current,
    ) {}
}
