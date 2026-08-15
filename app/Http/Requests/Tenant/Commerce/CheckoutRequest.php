<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Commerce;

use App\Http\Requests\BaseRequest;
use App\Models\Tenant\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        $customerId = $customer?->id;

        return [
            'shipping_address_id' => [
                'required',
                'integer',
                Rule::exists('customer_addresses', 'id')->where(
                    fn ($query) => $customerId !== null
                        ? $query->where('customer_id', $customerId)
                        : $query->whereRaw('1 = 0'),
                ),
            ],
            'billing_address_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('customer_addresses', 'id')->where(
                    fn ($query) => $customerId !== null
                        ? $query->where('customer_id', $customerId)
                        : $query->whereRaw('1 = 0'),
                ),
            ],
            'shipping_method_id' => ['sometimes', 'nullable', 'integer', 'exists:shipping_methods,id'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
