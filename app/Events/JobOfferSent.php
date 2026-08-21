<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\HR\JobOffer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobOfferSent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public JobOffer $offer,
        public ?string $publicToken = null,
    ) {}
}
