<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Catalog;

/**
 * Moderation status for a product review.
 */
enum ProductReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Spam = 'spam';
}
