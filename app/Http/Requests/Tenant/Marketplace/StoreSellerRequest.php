<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Marketplace;

use App\Enums\Tenant\Marketplace\CommissionType;
use App\Http\Requests\BaseRequest;
use App\Rules\MoneyAmount;
use Illuminate\Validation\Rule;

class StoreSellerRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('sellers', 'slug')],
            'description' => ['sometimes', 'nullable', 'string'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'commission_type' => ['sometimes', 'nullable', 'string', Rule::enum(CommissionType::class)],
            'commission_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'commission_fixed_amount' => ['sometimes', 'nullable', new MoneyAmount(allowZero: true, allowNull: true)],
        ];
    }
}
