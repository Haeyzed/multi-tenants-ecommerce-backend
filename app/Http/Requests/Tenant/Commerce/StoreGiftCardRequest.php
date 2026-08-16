<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Commerce;

use App\Http\Requests\BaseRequest;
use App\Rules\MoneyAmount;

class StoreGiftCardRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', new MoneyAmount],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
            'activate' => ['sometimes', 'boolean'],
            'customer_id' => ['sometimes', 'nullable', 'integer', 'exists:customers,id'],
            'purchased_order_id' => ['sometimes', 'nullable', 'integer', 'exists:orders,id'],
            'meta' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
