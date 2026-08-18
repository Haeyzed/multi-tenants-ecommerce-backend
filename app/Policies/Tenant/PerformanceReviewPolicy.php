<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\PerformanceReview;
use App\Models\Tenant\User;

/**
 * Authorization for performance reviews.
 */
class PerformanceReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.performance.view')
            || $user->can('hr.performance.manage')
            || $user->can('hr.view')
            || $user->can('hr.employees.view');
    }

    public function view(User $user, PerformanceReview $review): bool
    {
        if ($this->viewAny($user)) {
            return true;
        }

        return $user->employee !== null
            && in_array($user->employee->id, [$review->employee_id, $review->reviewer_id], true);
    }

    public function create(User $user): bool
    {
        return $user->can('hr.performance.manage');
    }

    public function update(User $user, PerformanceReview $review): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, PerformanceReview $review): bool
    {
        return $this->create($user);
    }
}
