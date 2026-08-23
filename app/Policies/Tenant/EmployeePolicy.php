<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\HR\Employee;
use App\Models\Tenant\User;

/**
 * Authorization for HR employees.
 */
class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.employees.view') || $user->can('hr.view');
    }

    public function view(User $user, Employee $employee): bool
    {
        if ($user->can('hr.employees.view') || $user->can('hr.view')) {
            return true;
        }

        return $employee->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('hr.employees.create');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can('hr.employees.update');
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->can('hr.employees.delete');
    }

    public function viewSalary(User $user, Employee $employee): bool
    {
        if ($user->can('hr.payroll.view') || $user->can('hr.payroll.manage') || $user->can('hr.view')) {
            return true;
        }

        return $employee->user_id === $user->id;
    }

    public function manageSalary(User $user, Employee $employee): bool
    {
        return $user->can('hr.payroll.manage');
    }
}
