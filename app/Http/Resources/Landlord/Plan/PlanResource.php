<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Plan;

use App\Http\Resources\Landlord\Feature\FeatureResource;
use App\Models\Landlord\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for landlord plans.
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
            'currency_id' => $plan->currency_id,
            'billing_interval' => $plan->billing_interval?->value ?? $plan->billing_interval,
            'billing_interval_count' => $plan->billing_interval_count,
            'trial_days' => $plan->trial_days,
            'is_active' => (bool) $plan->is_active,
            'is_public' => (bool) $plan->is_public,
            'is_free' => $plan->isFree(),
            'sort_order' => $plan->sort_order,
            'features' => FeatureResource::collection($this->whenLoaded('features')),
            'created_at' => $plan->created_at,
            'updated_at' => $plan->updated_at,
        ];
    }
}
