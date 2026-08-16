<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Commerce;

use App\Http\Requests\BaseRequest;
use App\Rules\MoneyAmount;
use Illuminate\Validation\Rule;

class StoreRefundRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_payment_id' => ['required', 'integer', Rule::exists('order_payments', 'id')],
            'amount' => ['sometimes', 'nullable', new MoneyAmount(allowNull: true)],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
