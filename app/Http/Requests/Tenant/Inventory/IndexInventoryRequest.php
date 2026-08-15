<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Inventory;

use App\Http\Requests\BaseRequest;

/**
 * Validates inventory index query params.
 */
class IndexInventoryRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'warehouse_location_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:50'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
