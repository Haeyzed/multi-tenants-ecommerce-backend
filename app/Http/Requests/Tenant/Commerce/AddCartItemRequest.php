<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Commerce;

use App\Http\Requests\BaseRequest;

/**
 * Validates adding a cart line item.
 */
class AddCartItemRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required_without:seller_offer_id', 'integer', 'exists:products,id'],
            'product_variant_id' => ['sometimes', 'nullable', 'integer', 'exists:product_variants,id'],
            'seller_offer_id' => ['sometimes', 'nullable', 'integer', 'exists:seller_offers,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
