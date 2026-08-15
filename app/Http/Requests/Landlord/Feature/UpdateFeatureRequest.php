<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Feature;

use App\Http\Requests\BaseRequest;
use App\Models\Landlord\Feature;
use Illuminate\Validation\Rule;

/**
 * Validates feature update payloads.
 */
class UpdateFeatureRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Feature|null $feature */
        $feature = $this->route('feature');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('features', 'slug')->ignore($feature?->id),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
