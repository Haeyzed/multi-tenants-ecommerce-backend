<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\HR\Attendance;
use App\Models\Tenant\User;

/**
 * Authorization for HR attendance records.
 */
class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.attendance.view')
            || $user->can('hr.attendance.manage')
            || $user->can('hr.view')
            || $user->employee()->exists();
    }

    public function view(User $user, Attendance $attendance): bool
    {
        if ($user->can('hr.attendance.view') || $user->can('hr.attendance.manage') || $user->can('hr.view')) {
            return true;
        }

        return $attendance->employee?->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('hr.attendance.manage') || $user->employee()->exists();
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return $user->can('hr.attendance.manage');
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->can('hr.attendance.manage');
    }

    public function clock(User $user): bool
    {
        return $user->can('hr.attendance.manage') || $user->employee()->exists();
    }
}
