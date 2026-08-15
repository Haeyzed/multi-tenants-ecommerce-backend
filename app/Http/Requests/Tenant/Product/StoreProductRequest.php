<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Product;

use App\Enums\Tenant\Catalog\ProductStatus;
use App\Enums\Tenant\Catalog\ProductType;
use App\Enums\Tenant\Catalog\ProductVisibility;
use App\Http\Requests\BaseRequest;
use App\Support\Media\MediaValidation;
use Illuminate\Validation\Rule;

/**
 * Validates product creation payloads (JSON or multipart).
 */
class StoreProductRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'brand_id' => ['sometimes', 'nullable', 'integer', Rule::exists('brands', 'id')],
            'unit_id' => ['sometimes', 'nullable', 'integer', Rule::exists('units', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('products', 'slug')],
            'description' => ['sometimes', 'nullable', 'string'],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'type' => ['sometimes', Rule::enum(ProductType::class)],
            'status' => ['sometimes', Rule::enum(ProductStatus::class)],
            'visibility' => ['sometimes', Rule::enum(ProductVisibility::class)],
            'has_variants' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'allow_backorder' => ['sometimes', 'boolean'],
            'is_preorder' => ['sometimes', 'boolean'],
            'preorder_start_at' => ['sometimes', 'nullable', 'date'],
            'preorder_end_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:preorder_start_at'],
            'minimum_purchase_quantity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'maximum_purchase_quantity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'tax_class' => ['sometimes', 'nullable', 'string', 'max:100'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'length' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'width' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'height' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'unpublished_at' => ['sometimes', 'nullable', 'date', 'after:published_at'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', Rule::exists('categories', 'id')],
            'price' => ['sometimes', 'array'],
            'price.currency' => ['required_with:price', 'string', 'size:3'],
            'price.amount' => ['required_with:price', 'numeric', 'min:0'],
            'price.compare_at_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'price.cost_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'price.is_active' => ['sometimes', 'boolean'],
            'price.starts_at' => ['sometimes', 'nullable', 'date'],
            'price.ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:price.starts_at'],
            'image' => MediaValidation::image(required: false),
            'images' => ['sometimes', 'array'],
            'images.*' => MediaValidation::image(required: true),
        ];
    }
}
