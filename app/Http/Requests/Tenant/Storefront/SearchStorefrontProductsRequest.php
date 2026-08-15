<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Storefront;

use App\Enums\Tenant\Catalog\ProductAvailability;
use App\Enums\Tenant\Catalog\ProductSearchSort;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for advanced storefront product search.
 */
class SearchStorefrontProductsRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['sometimes', 'nullable', 'string', 'max:255'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'brand_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'collection_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'tag_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'seller_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'min_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'gte:min_price'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'min_rating' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:5'],
            'is_featured' => ['sometimes', 'nullable', 'boolean'],
            'availability' => ['sometimes', 'nullable', Rule::enum(ProductAvailability::class)],
            'attribute_value_ids' => ['sometimes', 'nullable', 'array', 'max:20'],
            'attribute_value_ids.*' => ['integer', 'min:1'],
            'sort' => ['sometimes', 'nullable', Rule::enum(ProductSearchSort::class)],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
