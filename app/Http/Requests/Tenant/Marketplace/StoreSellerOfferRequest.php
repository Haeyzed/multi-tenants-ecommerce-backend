<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Marketplace;

use App\Enums\Tenant\Marketplace\SellerOfferStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreSellerOfferRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'seller_id' => ['required', 'integer', 'exists:sellers,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_variant_id' => ['sometimes', 'nullable', 'integer', 'exists:product_variants,id'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:100'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', Rule::enum(SellerOfferStatus::class)],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
