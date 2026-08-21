<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\HR\Department;
use App\Models\Tenant\User;

/**
 * Authorization for HR departments.
 */
class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.departments.view')
            || $user->can('hr.departments.manage')
            || $user->can('hr.view');
    }

    public function view(User $user, Department $department): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('hr.departments.manage');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->can('hr.departments.manage');
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->can('hr.departments.manage');
    }
}
