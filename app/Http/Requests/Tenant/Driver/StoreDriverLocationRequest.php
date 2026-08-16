<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Driver;

use App\Http\Requests\BaseRequest;

/**
 * Validates driver location update payloads.
 */
class StoreDriverLocationRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'delivery_id' => ['required', 'integer', 'exists:deliveries,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'heading' => ['sometimes', 'nullable', 'numeric', 'between:0,360'],
            'speed' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'recorded_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
