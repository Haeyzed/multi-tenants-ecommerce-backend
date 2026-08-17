<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\LeaveRequest;
use App\Models\Tenant\User;

/**
 * Authorization for HR leave requests.
 */
class LeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.leave.view')
            || $user->can('hr.leave.manage')
            || $user->can('hr.view')
            || $user->employee()->exists();
    }

    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($user->can('hr.leave.view') || $user->can('hr.leave.manage') || $user->can('hr.view')) {
            return true;
        }

        return $leaveRequest->employee?->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('hr.leave.manage') || $user->employee()->exists();
    }

    public function review(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->can('hr.leave.manage');
    }

    public function cancel(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($user->can('hr.leave.manage')) {
            return true;
        }

        return $leaveRequest->employee?->user_id === $user->id;
    }
}
