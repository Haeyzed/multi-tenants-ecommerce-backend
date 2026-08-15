<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Feature;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates feature creation payloads.
 */
class StoreFeatureRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('features', 'slug')],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
