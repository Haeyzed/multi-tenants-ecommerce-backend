<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\HR\LeaveType;
use App\Models\Tenant\User;

/**
 * Authorization for tenant leave types.
 */
class LeaveTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.leave.view')
            || $user->can('hr.leave.manage')
            || $user->can('hr.view')
            || $user->employee()->exists();
    }

    public function view(User $user, LeaveType $leaveType): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('hr.leave.manage');
    }

    public function update(User $user, LeaveType $leaveType): bool
    {
        return $user->can('hr.leave.manage');
    }

    public function delete(User $user, LeaveType $leaveType): bool
    {
        return $user->can('hr.leave.manage');
    }
}
