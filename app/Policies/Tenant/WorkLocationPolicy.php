<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\HR\WorkLocation;
use App\Models\Tenant\User;

/**
 * Authorization for HR work locations.
 */
class WorkLocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.work_locations.view')
            || $user->can('hr.work_locations.manage')
            || $user->can('hr.view');
    }

    public function view(User $user, WorkLocation $workLocation): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('hr.work_locations.manage');
    }

    public function update(User $user, WorkLocation $workLocation): bool
    {
        return $user->can('hr.work_locations.manage');
    }

    public function delete(User $user, WorkLocation $workLocation): bool
    {
        return $user->can('hr.work_locations.manage');
    }
}
