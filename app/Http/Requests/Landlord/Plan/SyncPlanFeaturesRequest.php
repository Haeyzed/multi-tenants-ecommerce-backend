<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Plan;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates syncing features onto a plan by slug.
 */
class SyncPlanFeaturesRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'features' => ['required', 'array'],
            'features.*.feature' => ['required_without:features.*.slug', 'string', 'max:255', Rule::exists('features', 'slug')],
            'features.*.slug' => ['required_without:features.*.feature', 'string', 'max:255', Rule::exists('features', 'slug')],
            'features.*.enabled' => ['sometimes', 'boolean'],
            'features.*.is_enabled' => ['sometimes', 'boolean'],
            'features.*.limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Normalize feature/enabled aliases to slug/is_enabled for downstream services.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (): void {
            $features = $this->input('features', []);

            if (! is_array($features)) {
                return;
            }

            $normalized = [];

            foreach ($features as $feature) {
                if (! is_array($feature)) {
                    continue;
                }

                $normalized[] = [
                    'slug' => $feature['feature'] ?? $feature['slug'] ?? null,
                    'is_enabled' => $feature['enabled'] ?? $feature['is_enabled'] ?? true,
                    'limit' => $feature['limit'] ?? null,
                ];
            }

            $this->merge(['features' => $normalized]);
        });
    }
}
