<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Commerce;

use App\Enums\Tenant\Commerce\PromotionType;
use App\Http\Requests\BaseRequest;
use App\Rules\MoneyAmount;
use Illuminate\Validation\Rule;

class StorePromotionRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('promotions', 'slug')],
            'description' => ['sometimes', 'nullable', 'string'],
            'type' => ['required', 'string', Rule::enum(PromotionType::class)],
            'value' => ['sometimes', new MoneyAmount(allowZero: true)],
            'min_order_amount' => ['sometimes', new MoneyAmount(allowZero: true)],
            'max_discount' => ['sometimes', 'nullable', new MoneyAmount(allowZero: true, allowNull: true)],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'is_exclusive' => ['sometimes', 'boolean'],
            'is_stackable' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'product_ids' => ['sometimes', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ];
    }
}
