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
 * Validates product update payloads (JSON or multipart).
 */
class UpdateProductRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var int|string|null $productId */
        $productId = $this->route('product');

        return [
            'brand_id' => ['sometimes', 'nullable', 'integer', Rule::exists('brands', 'id')],
            'unit_id' => ['sometimes', 'nullable', 'integer', Rule::exists('units', 'id')],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'slug')->ignore($productId),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'type' => ['sometimes', Rule::enum(ProductType::class)],
            'status' => ['sometimes', Rule::enum(ProductStatus::class)],
            'visibility' => ['sometimes', Rule::enum(ProductVisibility::class)],
            'has_variants' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'tax_class' => ['sometimes', 'nullable', 'string', 'max:100'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'length' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'width' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'height' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', Rule::exists('categories', 'id')],
            'attribute_value_ids' => ['sometimes', 'array'],
            'attribute_value_ids.*' => ['integer', Rule::exists('product_attribute_values', 'id')],
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
