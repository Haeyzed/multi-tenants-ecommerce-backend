<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Marketplace;

/**
 * Seller onboarding verification lifecycle.
 */
enum SellerVerificationStatus: string
{
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
