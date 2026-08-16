<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Shipping;

use App\Http\Requests\BaseRequest;
use App\Rules\MoneyAmount;
use Illuminate\Validation\Rule;

class UpdateShippingMethodRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $methodId = $this->route('shipping_method')?->id ?? $this->route('shippingMethod')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('shipping_methods', 'code')->ignore($methodId)],
            'description' => ['sometimes', 'nullable', 'string'],
            'amount' => ['sometimes', new MoneyAmount(allowZero: true)],
            'min_order_amount' => ['sometimes', 'nullable', new MoneyAmount(allowZero: true, allowNull: true)],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'estimated_days_min' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'estimated_days_max' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
