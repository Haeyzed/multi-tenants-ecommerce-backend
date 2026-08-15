<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Product;

use App\Http\Requests\BaseRequest;

/**
 * Validates product specification sync payloads.
 */
class SyncProductSpecificationsRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'specifications' => ['required', 'array'],
            'specifications.*.group' => ['sometimes', 'nullable', 'string', 'max:255'],
            'specifications.*.name' => ['required', 'string', 'max:255'],
            'specifications.*.value' => ['required', 'string'],
            'specifications.*.sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
