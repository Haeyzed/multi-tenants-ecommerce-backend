<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Commerce;

use App\Enums\Tenant\Commerce\OrderPaymentStatus;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates admin order index filters.
 */
class IndexOrderRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', Rule::enum(OrderStatus::class)],
            'payment_status' => ['sometimes', 'nullable', 'string', Rule::enum(OrderPaymentStatus::class)],
            'customer_id' => ['sometimes', 'nullable', 'integer', 'exists:customers,id'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:50'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
