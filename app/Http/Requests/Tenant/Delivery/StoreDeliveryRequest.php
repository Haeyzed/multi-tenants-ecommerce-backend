<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Delivery;

use App\Http\Requests\BaseRequest;

/**
 * Validates staff delivery creation payloads.
 */
class StoreDeliveryRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'shipment_id' => ['sometimes', 'nullable', 'integer', 'exists:shipments,id'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
