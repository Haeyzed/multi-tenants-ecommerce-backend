<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\Models\Tenant\CommerceEvent;
use App\Models\Tenant\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Records commerce analytics events for reporting foundations.
 */
class CommerceAnalyticsService
{
    /**
     * Persist a commerce analytics event when the analytics table is available.
     *
     * @param  string  $name
     * @param  ?Model  $subject
     * @param  ?Customer  $customer
     * @param  array<string, mixed>  $payload
     * @return CommerceEvent
     */
    public function record(string $name, ?Model $subject, ?Customer $customer, array $payload = []): CommerceEvent
    {
        $attributes = [
            'event_name' => $name,
            'customer_id' => $customer?->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'payload' => $payload !== [] ? $payload : null,
            'occurred_at' => now(),
        ];

        if (! Schema::hasTable('commerce_events')) {
            return CommerceEvent::make($attributes);
        }

        return CommerceEvent::query()->create($attributes);
    }
}
