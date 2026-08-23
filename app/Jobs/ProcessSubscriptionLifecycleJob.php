<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Landlord\Subscription\SubscriptionLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Daily sweep: subscription.expiring reminders and subscription.expired transitions.
 */
class ProcessSubscriptionLifecycleJob implements ShouldQueue
{
    use Queueable;

    public function handle(SubscriptionLifecycleService $lifecycle): void
    {
        $lifecycle->process();
    }
}
