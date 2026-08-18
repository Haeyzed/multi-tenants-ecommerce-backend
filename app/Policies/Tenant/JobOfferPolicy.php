<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\JobOffer;
use App\Models\Tenant\User;

class JobOfferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.recruitment.view')
            || $user->can('hr.recruitment.manage')
            || $user->can('hr.view');
    }

    public function view(User $user, JobOffer $offer): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('hr.recruitment.manage');
    }

    public function update(User $user, JobOffer $offer): bool
    {
        return $this->create($user);
    }

    public function approve(User $user, JobOffer $offer): bool
    {
        return $user->can('hr.recruitment.offers.approve') || $user->can('hr.recruitment.manage');
    }

    public function send(User $user, JobOffer $offer): bool
    {
        return $this->create($user);
    }
}
