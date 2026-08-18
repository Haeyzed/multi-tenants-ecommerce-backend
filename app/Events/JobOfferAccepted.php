<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\JobOffer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobOfferAccepted
{
    use Dispatchable, SerializesModels;

    public function __construct(public JobOffer $offer) {}
}
