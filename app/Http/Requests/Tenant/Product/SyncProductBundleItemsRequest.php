<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Product;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates bundle items sync payloads.
 */
class SyncProductBundleItemsRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'items.*.product_variant_id' => ['sometimes', 'nullable', 'integer', Rule::exists('product_variants', 'id')],
            'items.*.quantity' => ['sometimes', 'integer', 'min:1'],
            'items.*.sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
