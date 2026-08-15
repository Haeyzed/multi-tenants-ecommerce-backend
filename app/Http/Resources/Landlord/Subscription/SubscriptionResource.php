<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Subscription;

use App\Http\Resources\Landlord\Plan\PlanResource;
use App\Models\Landlord\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for landlord subscriptions.
 *
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Subscription $subscription */
        $subscription = $this->resource;

        return [
            'id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'plan_id' => $subscription->plan_id,
            'provider' => $subscription->provider?->value ?? $subscription->provider,
            'provider_subscription_id' => $subscription->provider_subscription_id,
            'provider_customer_id' => $subscription->provider_customer_id,
            'status' => $subscription->status?->value ?? $subscription->status,
            'starts_at' => $subscription->starts_at,
            'ends_at' => $subscription->ends_at,
            'trial_ends_at' => $subscription->trial_ends_at,
            'cancelled_at' => $subscription->cancelled_at,
            'current_period_start' => $subscription->current_period_start,
            'current_period_end' => $subscription->current_period_end,
            'auto_renew' => (bool) $subscription->auto_renew,
            'cancel_at_period_end' => (bool) $subscription->cancel_at_period_end,
            'metadata' => $subscription->metadata,
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'created_at' => $subscription->created_at,
            'updated_at' => $subscription->updated_at,
        ];
    }
}
