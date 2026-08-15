<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Plan;

use App\Enums\Landlord\BillingInterval;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates plan creation payloads.
 */
class StorePlanRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('plans', 'slug')],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'currency_id' => ['sometimes', 'nullable', 'integer'],
            'billing_interval' => ['sometimes', Rule::enum(BillingInterval::class)],
            'billing_interval_count' => ['sometimes', 'integer', 'min:1'],
            'trial_days' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'features' => ['sometimes', 'array'],
            'features.*.slug' => ['required_with:features', 'string', 'max:255', Rule::exists('features', 'slug')],
            'features.*.is_enabled' => ['sometimes', 'boolean'],
            'features.*.limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
