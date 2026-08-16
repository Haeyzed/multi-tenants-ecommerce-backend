<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Product\ProductVariant;

use App\Http\Requests\BaseRequest;
use App\Rules\MoneyAmount;
use App\Support\Media\MediaValidation;
use Illuminate\Validation\Rule;

/**
 * Validates product variant update payloads.
 */
class UpdateProductVariantRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var int|string|null $variantId */
        $variantId = $this->route('variant');

        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('product_variants', 'sku')->ignore($variantId),
            ],
            'barcode' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                Rule::unique('product_variants', 'barcode')->ignore($variantId),
            ],
            'unit_id' => ['sometimes', 'nullable', 'integer', Rule::exists('units', 'id')],
            'is_active' => ['sometimes', 'boolean'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'length' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'width' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'height' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'option_value_ids' => ['sometimes', 'array'],
            'option_value_ids.*' => ['integer', Rule::exists('product_option_values', 'id')],
            'price' => ['sometimes', 'array'],
            'price.currency' => ['required_with:price', 'string', 'size:3'],
            'price.amount' => ['required_with:price', new MoneyAmount(allowZero: true)],
            'price.compare_at_amount' => ['sometimes', 'nullable', new MoneyAmount(allowZero: true, allowNull: true)],
            'price.cost_amount' => ['sometimes', 'nullable', new MoneyAmount(allowZero: true, allowNull: true)],
            'price.is_active' => ['sometimes', 'boolean'],
            'price.starts_at' => ['sometimes', 'nullable', 'date'],
            'price.ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:price.starts_at'],
            'image' => MediaValidation::image(required: false),
        ];
    }
}
