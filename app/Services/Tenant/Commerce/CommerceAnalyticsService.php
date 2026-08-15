<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\Models\Tenant\CommerceEvent;
use App\Models\Tenant\Customer;
use Illuminate\Database\Eloquent\Model;

/**
 * Records commerce analytics events for reporting foundations.
 */
class CommerceAnalyticsService
{
    /**
     * Persist a commerce analytics event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(string $name, ?Model $subject, ?Customer $customer, array $payload = []): CommerceEvent
    {
        return CommerceEvent::query()->create([
            'event_name' => $name,
            'customer_id' => $customer?->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'payload' => $payload !== [] ? $payload : null,
            'occurred_at' => now(),
        ]);
    }
}
