<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Commerce;

use App\Enums\Tenant\Commerce\CouponType;
use App\Http\Requests\BaseRequest;
use App\Rules\MoneyAmount;
use Illuminate\Validation\Rule;

class UpdateCouponRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $couponId = $this->route('coupon')?->id;

        return [
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($couponId)->withoutTrashed()],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', 'string', Rule::enum(CouponType::class)],
            'value' => ['sometimes', new MoneyAmount(allowZero: true)],
            'minimum_order_amount' => ['sometimes', new MoneyAmount(allowZero: true)],
            'maximum_discount' => ['sometimes', 'nullable', new MoneyAmount(allowZero: true, allowNull: true)],
            'usage_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'product_ids' => ['sometimes', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ];
    }
}
