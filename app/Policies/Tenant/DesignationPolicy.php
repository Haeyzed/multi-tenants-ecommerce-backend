<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\HR\Designation;
use App\Models\Tenant\User;

/**
 * Authorization for HR designations.
 */
class DesignationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.designations.view')
            || $user->can('hr.designations.manage')
            || $user->can('hr.view');
    }

    public function view(User $user, Designation $designation): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('hr.designations.manage');
    }

    public function update(User $user, Designation $designation): bool
    {
        return $user->can('hr.designations.manage');
    }

    public function delete(User $user, Designation $designation): bool
    {
        return $user->can('hr.designations.manage');
    }
}
