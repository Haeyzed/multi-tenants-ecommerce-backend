<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Catalog;

use App\Http\Requests\BaseRequest;

/**
 * Validates product attribute value update payloads.
 */
class UpdateProductAttributeValueRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'value' => ['sometimes', 'required', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
