<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Commerce;

use App\Http\Requests\BaseRequest;

/**
 * Validates checkout payload.
 */
class CheckoutRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shipping_address_id' => ['required', 'integer', 'exists:customer_addresses,id'],
            'billing_address_id' => ['sometimes', 'nullable', 'integer', 'exists:customer_addresses,id'],
            'shipping_method_id' => ['sometimes', 'nullable', 'integer', 'exists:shipping_methods,id'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
