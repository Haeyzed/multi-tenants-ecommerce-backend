<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Tenant;

use App\Enums\Landlord\TenantStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validates landlord tenant creation payloads.
 */
class StoreTenantRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('tenants', 'slug')],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'status' => ['sometimes', Rule::enum(TenantStatus::class)],
            'is_active' => ['sometimes', 'boolean'],
            'domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)+$/i', Rule::unique('domains', 'domain')],
            'admin' => ['required', 'array'],
            'admin.first_name' => ['required', 'string', 'max:255'],
            'admin.last_name' => ['required', 'string', 'max:255'],
            'admin.email' => ['required', 'email', 'max:255'],
            'admin.phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'admin.password' => ['required', 'string', Password::defaults()],
            'profile' => ['sometimes', 'array'],
            'profile.display_name' => ['sometimes', 'string', 'max:255'],
            'profile.description' => ['sometimes', 'nullable', 'string'],
            'profile.is_public' => ['sometimes', 'boolean'],
        ];
    }
}
