<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Pos;

use App\Http\Requests\BaseRequest;

class RefundPosSaleRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:255'],
            'order_payment_id' => ['nullable', 'integer', 'exists:order_payments,id'],
        ];
    }
}
