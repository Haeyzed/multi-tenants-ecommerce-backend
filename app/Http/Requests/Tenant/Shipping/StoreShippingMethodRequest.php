<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Shipping;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreShippingMethodRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('shipping_methods', 'code')],
            'description' => ['sometimes', 'nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0'],
            'min_order_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'estimated_days_min' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'estimated_days_max' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
