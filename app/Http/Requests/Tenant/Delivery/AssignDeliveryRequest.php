<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Delivery;

use App\Http\Requests\BaseRequest;

/**
 * Validates delivery assignment payloads.
 */
class AssignDeliveryRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
        ];
    }
}
