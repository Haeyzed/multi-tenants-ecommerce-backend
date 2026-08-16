<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Employee;
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
        return $user->can('hr.employees.view') || $user->can('hr.view');
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
}
