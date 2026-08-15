<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Commerce;

use App\Http\Requests\BaseRequest;

class InitializeOrderPaymentRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ];
    }
}
