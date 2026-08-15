<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Tenant;

use App\Http\Requests\BaseRequest;

/**
 * Validates landlord tenant index query params.
 */
class IndexTenantRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
