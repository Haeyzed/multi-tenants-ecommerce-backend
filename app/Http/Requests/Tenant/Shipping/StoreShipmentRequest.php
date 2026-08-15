<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Shipping;

use App\Enums\Tenant\Commerce\ShipmentStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreShipmentRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'shipping_method_id' => ['sometimes', 'nullable', 'integer', 'exists:shipping_methods,id'],
            'tracking_number' => ['sometimes', 'nullable', 'string', 'max:191'],
            'carrier' => ['sometimes', 'nullable', 'string', 'max:191'],
            'tracking_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::enum(ShipmentStatus::class)],
        ];
    }
}
