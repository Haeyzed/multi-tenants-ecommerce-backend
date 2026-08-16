<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\CustomerSegment;
use App\Models\Tenant\User;

/**
 * Authorization for tenant customer segment actions.
 */
class CustomerSegmentPolicy
{
    /**
     * Determine whether the user can list segments.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('segments.view');
    }

    /**
     * Determine whether the user can view a segment.
     */
    public function view(User $user, CustomerSegment $customerSegment): bool
    {
        return $user->can('segments.view');
    }

    /**
     * Determine whether the user can create or mutate segments.
     */
    public function create(User $user): bool
    {
        return $user->can('segments.manage');
    }

    /**
     * Determine whether the user can update a segment.
     */
    public function update(User $user, CustomerSegment $customerSegment): bool
    {
        return $user->can('segments.manage');
    }

    /**
     * Determine whether the user can delete a segment.
     */
    public function delete(User $user, CustomerSegment $customerSegment): bool
    {
        return $user->can('segments.manage');
    }
}
