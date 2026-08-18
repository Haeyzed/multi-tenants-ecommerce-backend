<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\PerformanceCycle;
use App\Models\Tenant\User;

/**
 * Authorization for performance cycles.
 */
class PerformanceCyclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.performance.view')
            || $user->can('hr.performance.manage')
            || $user->can('hr.view');
    }

    public function view(User $user, PerformanceCycle $cycle): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('hr.performance.manage');
    }

    public function update(User $user, PerformanceCycle $cycle): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, PerformanceCycle $cycle): bool
    {
        return $this->create($user);
    }
}
