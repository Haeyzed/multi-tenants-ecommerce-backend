<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Product;

use App\Enums\Tenant\Catalog\ProductStatus;
use App\Enums\Tenant\Catalog\ProductType;
use App\Enums\Tenant\Catalog\ProductVisibility;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates product index query params.
 */
class IndexProductRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', Rule::enum(ProductStatus::class)],
            'type' => ['sometimes', 'nullable', Rule::enum(ProductType::class)],
            'visibility' => ['sometimes', 'nullable', Rule::enum(ProductVisibility::class)],
            'brand_id' => ['sometimes', 'nullable', 'integer', Rule::exists('brands', 'id')],
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('categories', 'id')],
            'is_featured' => ['sometimes', 'nullable', 'boolean'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:50'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
