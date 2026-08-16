<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Marketplace;

use App\Enums\Tenant\Marketplace\SellerOfferStatus;
use App\Http\Requests\BaseRequest;
use App\Rules\MoneyAmount;
use Illuminate\Validation\Rule;

class UpdateSellerOfferRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sku' => ['sometimes', 'nullable', 'string', 'max:100'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'price' => ['sometimes', new MoneyAmount(allowZero: true)],
            'compare_at_price' => ['sometimes', 'nullable', new MoneyAmount(allowZero: true, allowNull: true)],
            'cost' => ['sometimes', 'nullable', new MoneyAmount(allowZero: true, allowNull: true)],
            'status' => ['sometimes', 'string', Rule::enum(SellerOfferStatus::class)],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
