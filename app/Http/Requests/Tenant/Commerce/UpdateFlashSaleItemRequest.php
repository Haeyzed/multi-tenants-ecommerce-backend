<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Commerce;

use App\Http\Requests\BaseRequest;
use App\Rules\MoneyAmount;

class UpdateFlashSaleItemRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['sometimes', 'integer', 'exists:products,id'],
            'product_variant_id' => ['sometimes', 'nullable', 'integer', 'exists:product_variants,id'],
            'sale_price' => ['sometimes', new MoneyAmount],
            'qty_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'customer_group_id' => ['sometimes', 'nullable', 'integer', 'exists:customer_groups,id'],
            'customer_segment_id' => ['sometimes', 'nullable', 'integer', 'exists:customer_segments,id'],
        ];
    }
}
