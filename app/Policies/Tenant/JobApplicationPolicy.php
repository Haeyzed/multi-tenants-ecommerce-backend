<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\HR\JobApplication;
use App\Models\Tenant\User;

/**
 * Authorization for recruitment applications.
 */
class JobApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.recruitment.view')
            || $user->can('hr.recruitment.manage')
            || $user->can('hr.view');
    }

    public function view(User $user, JobApplication $application): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('hr.recruitment.manage');
    }

    public function update(User $user, JobApplication $application): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, JobApplication $application): bool
    {
        return $this->create($user);
    }

    public function moveStage(User $user, JobApplication $application): bool
    {
        return $user->can('hr.recruitment.stage') || $user->can('hr.recruitment.manage');
    }

    public function hire(User $user, JobApplication $application): bool
    {
        return $user->can('hr.recruitment.hire') || $user->can('hr.recruitment.manage');
    }
}
