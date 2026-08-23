<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\HR\Employee;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an HR employee profile is created.
 */
class EmployeeCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Employee $employee) {}
}
