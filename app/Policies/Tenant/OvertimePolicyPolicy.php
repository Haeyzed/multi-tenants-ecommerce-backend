<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\OvertimePolicy;
use App\Models\Tenant\User;

/**
 * Authorization for overtime policies.
 */
class OvertimePolicyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.view')
            || $user->can('hr.payroll.view')
            || $user->can('hr.attendance.view')
            || $user->can('hr.settings.view');
    }

    public function view(User $user, OvertimePolicy $policy): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('hr.settings.update') || $user->can('hr.payroll.manage');
    }

    public function update(User $user, OvertimePolicy $policy): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, OvertimePolicy $policy): bool
    {
        return $this->create($user);
    }
}
