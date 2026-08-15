<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Commerce;

use App\Enums\Tenant\Commerce\ReturnReason;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreOrderReturnRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['sometimes', 'nullable', 'string', Rule::enum(ReturnReason::class)],
            'customer_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.reason' => ['sometimes', 'nullable', 'string', Rule::enum(ReturnReason::class)],
        ];
    }
}
