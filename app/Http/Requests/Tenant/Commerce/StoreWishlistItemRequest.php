<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Commerce;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates adding an item to the customer wishlist.
 */
class StoreWishlistItemRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'product_variant_id' => ['sometimes', 'nullable', 'integer', Rule::exists('product_variants', 'id')],
        ];
    }
}
