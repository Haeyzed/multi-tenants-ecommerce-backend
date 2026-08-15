<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Plan;

use App\Http\Requests\BaseRequest;

/**
 * Validates plan index query params.
 */
class IndexPlanRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'is_public' => ['sometimes', 'nullable', 'boolean'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
