<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Marketplace;

use App\Enums\Tenant\Marketplace\CommissionType;
use App\Http\Requests\BaseRequest;
use App\Rules\MoneyAmount;
use Illuminate\Validation\Rule;

/**
 * Validates seller group update payloads.
 */
class UpdateSellerGroupRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var int|string|null $groupId */
        $groupId = $this->route('seller_group');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('seller_groups', 'name')->ignore($groupId),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'commission_type' => ['sometimes', 'nullable', 'string', Rule::enum(CommissionType::class)],
            'commission_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'commission_fixed_amount' => ['sometimes', 'nullable', new MoneyAmount(allowZero: true, allowNull: true)],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
