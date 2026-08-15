<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\ProductReview;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductReviewApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(public ProductReview $review) {}
}
