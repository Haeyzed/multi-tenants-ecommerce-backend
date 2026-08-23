<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\HR\Interview;
use App\Models\Tenant\User;

class InterviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.recruitment.view')
            || $user->can('hr.recruitment.manage')
            || $user->can('hr.recruitment.feedback')
            || $user->can('hr.view');
    }

    public function view(User $user, Interview $interview): bool
    {
        return $user->can('hr.recruitment.view')
            || $user->can('hr.recruitment.manage')
            || $user->can('hr.view')
            || $interview->interviewers()->whereKey($user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('hr.recruitment.manage');
    }

    public function update(User $user, Interview $interview): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Interview $interview): bool
    {
        return $this->create($user);
    }

    public function feedback(User $user, Interview $interview): bool
    {
        return $user->can('hr.recruitment.feedback')
            || $user->can('hr.recruitment.manage')
            || $interview->interviewers()->whereKey($user->id)->exists();
    }
}
