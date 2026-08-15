<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Catalog;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates product attribute update payloads.
 */
class UpdateProductAttributeRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var int|string|null $attributeId */
        $attributeId = $this->route('attribute');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('product_attributes', 'name')->ignore($attributeId)],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('product_attributes', 'slug')->ignore($attributeId)],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
