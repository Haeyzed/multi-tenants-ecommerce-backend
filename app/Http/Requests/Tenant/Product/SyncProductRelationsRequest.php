<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Product;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates product relation sync payloads.
 */
class SyncProductRelationsRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'items.*.sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
