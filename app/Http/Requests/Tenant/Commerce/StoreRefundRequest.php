<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Commerce;

use App\Http\Requests\BaseRequest;
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
            'amount' => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
