<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Pos;

use App\Http\Requests\BaseRequest;

class CreatePosSaleRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string', 'in:cash,card,bank_transfer,gateway,offline_card,offline_bank'],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.gateway' => ['nullable', 'string', 'max:50'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_fixed' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
