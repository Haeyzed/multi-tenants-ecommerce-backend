<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Commerce;

use App\Http\Requests\BaseRequest;
use App\Rules\MoneyAmount;
use Illuminate\Validation\Rule;

class StoreFlashSaleRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('flash_sales', 'slug')],
            'description' => ['sometimes', 'nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
            'stack_with_coupons' => ['sometimes', 'boolean'],
            'items' => ['sometimes', 'array'],
            'items.*.product_id' => ['required_with:items', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['sometimes', 'nullable', 'integer', 'exists:product_variants,id'],
            'items.*.sale_price' => ['required_with:items', new MoneyAmount],
            'items.*.qty_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'items.*.per_customer_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'items.*.customer_group_id' => ['sometimes', 'nullable', 'integer', 'exists:customer_groups,id'],
            'items.*.customer_segment_id' => ['sometimes', 'nullable', 'integer', 'exists:customer_segments,id'],
        ];
    }
}
