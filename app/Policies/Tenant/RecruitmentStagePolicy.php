<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\HR\RecruitmentStage;
use App\Models\Tenant\User;

class RecruitmentStagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.recruitment.view')
            || $user->can('hr.recruitment.manage')
            || $user->can('hr.view');
    }

    public function view(User $user, RecruitmentStage $stage): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('hr.recruitment.manage');
    }

    public function update(User $user, RecruitmentStage $stage): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, RecruitmentStage $stage): bool
    {
        return $this->create($user);
    }
}
