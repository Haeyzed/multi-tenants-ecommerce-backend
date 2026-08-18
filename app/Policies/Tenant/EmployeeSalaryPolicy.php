<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\EmployeeSalary;
use App\Models\Tenant\User;

/**
 * Authorization for employee salary configuration.
 */
class EmployeeSalaryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.payroll.view') || $user->can('hr.payroll.manage') || $user->can('hr.view');
    }

    public function view(User $user, ?EmployeeSalary $employeeSalary = null): bool
    {
        return $user->can('hr.payroll.view') || $user->can('hr.payroll.manage') || $user->can('hr.view');
    }

    public function manage(User $user): bool
    {
        return $user->can('hr.payroll.manage');
    }
}
