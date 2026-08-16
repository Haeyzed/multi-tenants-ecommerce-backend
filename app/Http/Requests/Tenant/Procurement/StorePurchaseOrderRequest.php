<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Procurement;

use App\Http\Requests\BaseRequest;
use App\Rules\MoneyAmount;

class StorePurchaseOrderRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'expected_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['sometimes', 'nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', new MoneyAmount(allowZero: true)],
            'items.*.tax' => ['sometimes', 'nullable', new MoneyAmount(allowZero: true, allowNull: true)],
        ];
    }
}
