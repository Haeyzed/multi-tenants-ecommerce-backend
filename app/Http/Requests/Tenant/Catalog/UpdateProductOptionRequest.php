<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Catalog;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates product option update payloads.
 */
class UpdateProductOptionRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var int|string|null $optionId */
        $optionId = $this->route('option');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('product_options', 'name')->ignore($optionId)],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('product_options', 'slug')->ignore($optionId)],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
