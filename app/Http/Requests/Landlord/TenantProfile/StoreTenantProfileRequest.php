<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\TenantProfile;

use App\Http\Requests\BaseRequest;
use App\Support\Media\MediaValidation;
use Illuminate\Validation\Rule;

/**
 * Validates landlord tenant profile creation payloads.
 */
class StoreTenantProfileRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('tenant_profiles', 'slug')],
            'description' => ['sometimes', 'nullable', 'string'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country_id' => ['sometimes', 'nullable', 'integer'],
            'state_id' => ['sometimes', 'nullable', 'integer'],
            'city_id' => ['sometimes', 'nullable', 'integer'],
            'currency_id' => ['sometimes', 'nullable', 'integer'],
            'language_id' => ['sometimes', 'nullable', 'integer'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_public' => ['sometimes', 'boolean'],
            'logo' => MediaValidation::image(required: false),
            'cover' => MediaValidation::image(required: false),
            'banner' => MediaValidation::image(required: false),
        ];
    }
}
