<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Inventory;

use App\Http\Requests\BaseRequest;

/**
 * Validates assigning a product or variant to a warehouse.
 */
class StoreInventoryRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_variant_id' => ['sometimes', 'nullable', 'integer', 'exists:product_variants,id'],
            'warehouse_location_id' => ['sometimes', 'nullable', 'integer', 'exists:warehouse_locations,id'],
            'quantity' => ['sometimes', 'integer', 'min:0'],
            'reorder_level' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'reorder_quantity' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
