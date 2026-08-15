<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Commerce;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates wishlist product check query parameters.
 */
class CheckWishlistProductRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_variant_id' => ['sometimes', 'nullable', 'integer', Rule::exists('product_variants', 'id')],
        ];
    }
}
