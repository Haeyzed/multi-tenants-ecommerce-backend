<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Landlord\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionActivated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Subscription $subscription) {}
}
