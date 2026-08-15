<?php

declare(strict_types=1);

namespace App\Http\Resources\Public\Plan;

use App\Models\Landlord\Feature;
use App\Models\Landlord\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public pricing-page plan resource with feature enabled/limit flags.
 *
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Plan $plan */
        $plan = $this->resource;

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'description' => $plan->description,
            'price' => $plan->price,
            'currency' => $plan->currency,
            'billing_interval' => $plan->billing_interval?->value ?? $plan->billing_interval,
            'billing_interval_count' => $plan->billing_interval_count,
            'trial_days' => $plan->trial_days,
            'is_free' => $plan->isFree(),
            'sort_order' => $plan->sort_order,
            'features' => $this->whenLoaded('features', function () use ($plan) {
                return $plan->features->map(function (Feature $feature): array {
                    return [
                        'name' => $feature->name,
                        'slug' => $feature->slug,
                        'description' => $feature->description,
                        'enabled' => (bool) ($feature->pivot?->is_enabled ?? false),
                        'limit' => $feature->pivot?->limit !== null ? (int) $feature->pivot->limit : null,
                    ];
                })->values()->all();
            }),
        ];
    }
}
