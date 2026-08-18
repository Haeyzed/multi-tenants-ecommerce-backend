<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\JobOpening;
use App\Models\Tenant\User;

/**
 * Authorization for recruitment job openings.
 */
class JobOpeningPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.recruitment.view')
            || $user->can('hr.recruitment.manage')
            || $user->can('hr.view');
    }

    public function view(User $user, JobOpening $opening): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('hr.recruitment.manage');
    }

    public function update(User $user, JobOpening $opening): bool
    {
        return $this->create($user);
    }

    public function publish(User $user, JobOpening $opening): bool
    {
        return $user->can('hr.recruitment.publish') || $user->can('hr.recruitment.manage');
    }

    public function delete(User $user, JobOpening $opening): bool
    {
        return $this->create($user);
    }
}
