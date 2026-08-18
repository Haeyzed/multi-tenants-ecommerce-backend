<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Candidate;
use App\Models\Tenant\User;

class CandidatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.recruitment.view')
            || $user->can('hr.recruitment.manage')
            || $user->can('hr.view');
    }

    public function view(User $user, Candidate $candidate): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('hr.recruitment.manage');
    }

    public function update(User $user, Candidate $candidate): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Candidate $candidate): bool
    {
        return $this->create($user);
    }
}
