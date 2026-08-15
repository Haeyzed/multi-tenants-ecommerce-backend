<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Catalog;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates collection product sync payloads.
 */
class SyncCollectionProductsRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'products' => ['required', 'array'],
            'products.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'products.*.sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
