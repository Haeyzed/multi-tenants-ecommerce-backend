<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\ProductReview;
use App\Models\Tenant\User;

/**
 * Authorization for tenant product review moderation.
 */
class ProductReviewPolicy
{
    /**
     * Determine whether the user can list reviews.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('reviews.view');
    }

    /**
     * Determine whether the user can view a review.
     */
    public function view(User $user, ProductReview $review): bool
    {
        return $user->can('reviews.view');
    }

    /**
     * Determine whether the user can moderate a review.
     */
    public function moderate(User $user, ProductReview $review): bool
    {
        return $user->can('reviews.moderate');
    }

    /**
     * Determine whether the user can delete a review.
     */
    public function delete(User $user, ProductReview $review): bool
    {
        return $user->can('reviews.delete');
    }
}
