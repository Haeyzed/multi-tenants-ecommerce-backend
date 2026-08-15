<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Warehouse;

use App\Http\Requests\BaseRequest;

/**
 * Validates warehouse location update payloads.
 */
class UpdateWarehouseLocationRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'aisle' => ['sometimes', 'nullable', 'string', 'max:50'],
            'rack' => ['sometimes', 'nullable', 'string', 'max:50'],
            'shelf' => ['sometimes', 'nullable', 'string', 'max:50'],
            'bin' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
